#!/usr/bin/env bash
#
# Install WordPress test suite for plugin unit testing
#
# Usage: ./bin/install-wp-tests.sh db_name db_user db_pass [db_host] [wp_version] [skip_database]
#

if [ $# -lt 3 ]; then
    echo "Usage: $0 db_name db_user db_pass [db_host] [wp_version] [skip_database]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-?(.*)$ ]]; then
    WP_TESTS_TAG="tags/${WP_VERSION}"
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
else
    # http serves a hierarchical listing of tags/branches
    download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
    LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | head -1 | sed 's/"version":"//')
    if [[ -z "$LATEST_VERSION" ]]; then
        echo "Latest WordPress version could not be found"
        exit 1
    fi
    WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

set -ex

install_wp() {
    if [ -d $WP_CORE_DIR ]; then
        return;
    fi

    mkdir -p $WP_CORE_DIR

    if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
        mkdir -p $TMPDIR/wordpress-trunk
        rm -rf $TMPDIR/wordpress-trunk/*
        svn export --quiet https://core.svn.wordpress.org/trunk $TMPDIR/wordpress-trunk/wordpress
        mv $TMPDIR/wordpress-trunk/wordpress/* $WP_CORE_DIR
    else
        if [ $WP_VERSION == 'latest' ]; then
            local ARCHIVE_NAME='latest'
        elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
            ARCHIVE_NAME="wordpress-$WP_VERSION"
        else
            ARCHIVE_NAME="wordpress-$WP_VERSION"
        fi
        download https://wordpress.org/${ARCHIVE_NAME}.tar.gz $TMPDIR/wordpress.tar.gz
        tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
    fi

    download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
    # portable in-place argument for both GNU sed and Mac OSX sed
    if [[ $(uname -s) == 'Darwin' ]]; then
        local ioption='-i.bak'
    else
        local ioption='-i'
    fi

    # set up testing suite if it doesn't yet exist
    if [ ! -d $WP_TESTS_DIR ]; then
        mkdir -p $WP_TESTS_DIR
        svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
        svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
    fi

    if [ ! -f wp-tests-config.php ]; then
        download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php $WP_TESTS_DIR/wp-tests-config.php
        # remove all forward slashes in the end
        WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
        sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" $WP_TESTS_DIR/wp-tests-config.php
        sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" $WP_TESTS_DIR/wp-tests-config.php
        sed $ioption "s/yourusernamehere/$DB_USER/" $WP_TESTS_DIR/wp-tests-config.php
        sed $ioption "s/yourpasswordhere/$DB_PASS/" $WP_TESTS_DIR/wp-tests-config.php
        sed $ioption "s|localhost|${DB_HOST}|" $WP_TESTS_DIR/wp-tests-config.php
    fi
}

install_db() {
    if [ ${SKIP_DB_CREATE} = "true" ]; then
        return 0
    fi

    # parse DB_HOST for port or socket references
    local PARTS=(${DB_HOST//\:/ })
    local DB_HOSTNAME=${PARTS[0]};
    local DB_SOCK_OR_PORT=${PARTS[1]};
    local EXTRA=""

    if ! [ -z $DB_SOCK_OR_PORT ] ; then
        if [[ "$DB_SOCK_OR_PORT" =~ ^[0-9]+$ ]] ; then
            EXTRA=" --port=$DB_SOCK_OR_PORT"
        elif [[ ${DB_SOCK_OR_PORT} == /* ]] ; then
            EXTRA=" --socket=$DB_SOCK_OR_PORT"
        fi
    fi

    # create database
    mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_wp
install_test_suite
install_db

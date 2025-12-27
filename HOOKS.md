# Developer Hooks Reference

Content Freshness Monitor provides action and filter hooks for developers to extend and customize the plugin's behavior.

## Filters

### `cfm_post_data`

Filter the prepared post data for stale content display.

**Since:** 2.7.0

**Parameters:**
- `$data` *(array)* - Prepared post data array with keys: ID, title, edit_link, view_link, post_type, modified, modified_ago, days_old, last_reviewed, author
- `$post` *(WP_Post)* - The original post object

**Example:**
```php
add_filter( 'cfm_post_data', function( $data, $post ) {
    // Add custom field to post data
    $data['custom_priority'] = get_post_meta( $post->ID, '_content_priority', true );
    return $data;
}, 10, 2 );
```

---

### `cfm_stats`

Filter the content freshness statistics.

**Since:** 2.7.0

**Parameters:**
- `$stats` *(array)* - Statistics array with keys: total, stale, fresh, stale_percent, threshold, per_type, cached_at

**Example:**
```php
add_filter( 'cfm_stats', function( $stats ) {
    // Add custom metric
    $stats['urgent'] = count_urgent_stale_posts();
    return $stats;
} );
```

---

### `cfm_health_score`

Filter the content health score data.

**Since:** 2.7.0

**Parameters:**
- `$health` *(array)* - Health score data with keys: score, grade, label, class
- `$stats` *(array|null)* - The statistics used to calculate the score

**Example:**
```php
add_filter( 'cfm_health_score', function( $health, $stats ) {
    // Apply custom scoring curve
    if ( $health['score'] >= 95 ) {
        $health['grade'] = 'A+';
        $health['label'] = __( 'Outstanding', 'my-plugin' );
    }
    return $health;
}, 10, 2 );
```

---

### `cfm_digest_email_args`

Filter the email arguments before sending the admin digest.

**Since:** 2.7.0

**Parameters:**
- `$email_args` *(array)* - Email arguments:
  - `recipient` *(string)* - Email recipient
  - `subject` *(string)* - Email subject
  - `message` *(string)* - Email body HTML
  - `headers` *(array)* - Email headers
- `$stats` *(array)* - Content statistics

**Example:**
```php
add_filter( 'cfm_digest_email_args', function( $email_args, $stats ) {
    // Add CC recipient
    $email_args['headers'][] = 'Cc: content-team@example.com';

    // Customize subject for critical situations
    if ( $stats['stale_percent'] > 50 ) {
        $email_args['subject'] = '[URGENT] ' . $email_args['subject'];
    }

    return $email_args;
}, 10, 2 );
```

---

## Actions

### `cfm_post_reviewed`

Fires after a post is marked as reviewed.

**Since:** 2.7.0

**Parameters:**
- `$post_id` *(int)* - The post ID that was reviewed
- `$reviewed_date` *(string)* - The review timestamp (MySQL datetime format)

**Example:**
```php
add_action( 'cfm_post_reviewed', function( $post_id, $reviewed_date ) {
    // Log the review action
    error_log( "Post {$post_id} marked as reviewed on {$reviewed_date}" );

    // Notify the post author
    $post = get_post( $post_id );
    $author_email = get_the_author_meta( 'user_email', $post->post_author );
    wp_mail( $author_email, 'Your post was reviewed', 'Content team reviewed: ' . $post->post_title );
}, 10, 2 );
```

---

### `cfm_bulk_posts_reviewed`

Fires after bulk posts are marked as reviewed.

**Since:** 2.7.0

**Parameters:**
- `$post_ids` *(array)* - Array of reviewed post IDs
- `$count` *(int)* - Number of posts reviewed
- `$reviewed_date` *(string)* - The review timestamp (MySQL datetime format)

**Example:**
```php
add_action( 'cfm_bulk_posts_reviewed', function( $post_ids, $count, $reviewed_date ) {
    // Log bulk review
    error_log( "Bulk reviewed {$count} posts on {$reviewed_date}" );

    // Trigger cache refresh
    do_action( 'my_plugin_refresh_content_cache' );
}, 10, 3 );
```

---

### `cfm_digest_email_sent`

Fires after the admin digest email is sent.

**Since:** 2.7.0

**Parameters:**
- `$recipient` *(string)* - Email recipient
- `$stats` *(array)* - Content statistics

**Example:**
```php
add_action( 'cfm_digest_email_sent', function( $recipient, $stats ) {
    // Log email sent
    error_log( "Content freshness digest sent to {$recipient}" );

    // Track in analytics
    if ( function_exists( 'my_analytics_track' ) ) {
        my_analytics_track( 'email_sent', array(
            'type' => 'content_freshness_digest',
            'stale_count' => $stats['stale'],
        ) );
    }
}, 10, 2 );
```

---

## Usage Tips

### Caching Considerations

When modifying statistics via the `cfm_stats` filter, be aware that stats are cached for 15 minutes. If you need to force a refresh:

```php
// Clear the stats cache
delete_transient( 'cfm_stats_cache' );
delete_transient( 'cfm_stale_count_cache' );
```

### Extending Post Data

The `cfm_post_data` filter is useful for adding custom columns to the admin table or including additional data in the REST API response:

```php
// Add data for custom admin column
add_filter( 'cfm_post_data', function( $data, $post ) {
    $data['word_count'] = str_word_count( strip_tags( $post->post_content ) );
    return $data;
}, 10, 2 );
```

### Custom Email Templates

To completely replace the email template, use the `cfm_digest_email_args` filter:

```php
add_filter( 'cfm_digest_email_args', function( $email_args, $stats ) {
    // Use custom template
    $email_args['message'] = my_custom_email_template( $stats );
    return $email_args;
}, 10, 2 );
```

---

## Related Resources

- [WordPress Plugin API](https://developer.wordpress.org/plugins/hooks/)
- [add_filter() Documentation](https://developer.wordpress.org/reference/functions/add_filter/)
- [add_action() Documentation](https://developer.wordpress.org/reference/functions/add_action/)

<?php
/*
Plugin Name: OpenAI Article Auto Generator for WordPress
Plugin URI: https://xunika.uk
Description: Generates text using OpenAI's text-davinci-003 model
Version: 1.2
Author: xunika.uk
Author URI: https://xunika.uk
License: GPL2
*/

// Schedule text generation
function openai_schedule_text_generation( $titles, $interval ) {
    $prompt_template = 'Write a 1000 words article about: %s';
    $now = time();
    foreach ( $titles as $title ) {
        $prompt = sprintf( $prompt_template, $title );
        if ( ! wp_next_scheduled( 'openai_generate_text', array( $prompt, $title ) ) ) {
            wp_schedule_single_event( $now, 'openai_generate_text', array( $prompt, $title ) );
            $now += $interval; // use the user-defined interval
        }
    }
}

function openai_do_generate_text( $prompt, $title ) {
    $openai_api_key = get_option( 'openai_api_key' );
    if ( ! $openai_api_key ) {
        error_log( 'Invalid OpenAI API key.' );
        return;
    }

    // Prepare the request data
    $data = array(
        'prompt' => $prompt . $title,
        'max_tokens' => 1024,
        'temperature' => 0.9,
        'n' => 1,
        'stop' => '\n'
    );

    // Convert the data to JSON
    $json_data = wp_json_encode( $data );

    // Send the request to OpenAI API
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, "https://api.openai.com/v1/engines/text-davinci-003/completions" );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_api_key
    ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    // Check for errors
    if ( $http_code !== 200 ) {
        error_log( 'OpenAI API request failed with HTTP error: ' . $http_code );
        return;
    }

    // Parse the response
    $response_body = json_decode( $response, true );
    $choices = $response_body['choices'];
    $text = $choices[0]['text'];

    // Get the HTML code for the image
    $image_html = '<img src="https://source.unsplash.com/1600x900/?random">';

    // Append the image to the generated text
    $text .= $image_html;

    // Create a new post with the generated text
    $post_id = wp_insert_post( array(
        'post_title' => $title,
        'post_content' => $text,
        'post_status' => 'publish'
    ) );

    // Check for errors
    if ( is_wp_error( $post_id ) || $post_id === 0 ) {
        error_log( 'Failed to create new post.' );
        return;
}

// Log the generated text for debugging purposes
error_log( 'Generated article: ' . $text );

// Return the post ID and generated text
return array(
    'post_id' => $post_id,
    'title' => $title,
    'text' => $text,
);
}

function openai_handle_text_generation( $prompt, $title ) {
openai_do_generate_text( $prompt, $title );
}
add_action( 'openai_generate_text', 'openai_handle_text_generation', 10, 2 );

// Add the settings page
// Add the settings page
function openai_settings_page() {
    // Handle form submission
    if ( isset( $_POST['openai_titles'] ) ) {
        $titles = explode( "\n", sanitize_textarea_field( $_POST['openai_titles'] ) );
        $interval = intval( $_POST['openai_interval'] ); // get the user-defined interval
        openai_schedule_text_generation( $titles, $interval ); // pass the interval to openai_schedule_text_generation function
        echo '<div class="notice notice-success"><p>Article generation scheduled successfully!</p></div>';
    }
    if ( isset( $_POST['openai_api_key'] ) ) {
        $api_key = sanitize_text_field( $_POST['openai_api_key'] );
        if ( $api_key === get_option( 'openai_api_key' ) ) {
            // The API Key is already saved, no need to show the success message.
        } else {
            update_option( 'openai_api_key', $api_key );
            echo '<div class="notice notice-success"><p>API key saved successfully!</p></div>';
        }
    }

    // Display the settings page
    ?>
    <div class="wrap">
        <h1>OpenAI Settings</h1>
        <form method="post" action="">
            <label for="openai_api_key">API Key:</label>
            <input type="text" name="openai_api_key" value="<?php echo esc_attr( get_option( 'openai_api_key' ) ); ?>"><br>
            <label for="openai_titles">Titles:</label>
            <textarea name="openai_titles"></textarea>
            <p class="description">Enter the titles for the generated articles, one per line.</p>
            <label for="openai_interval">Interval (seconds):</label>
            <input type="number" name="openai_interval" min="1" value="<?php echo esc_attr( get_option( 'openai_interval', 600 ) ); ?>">
            <p class="description">Enter the interval time (in seconds) between each article generation.</p>
            <?php submit_button( 'Generate Articles' ); ?>
        </form>
    </div>
    <?php
}
// Add the settings link to the plugin page
function openai_settings_link( $links ) {
$settings_link = '<a href="options-general.php?page=openai-settings">Settings</a>';
array_push( $links, $settings_link );
return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'openai_settings_link' );

// Add the settings page to the WordPress menu
function openai_settings_menu() {
add_options_page( 'OpenAI Settings', 'OpenAI Settings', 'manage_options', 'openai-settings', 'openai_settings_page' );
}
add_action( 'admin_menu', 'openai_settings_menu' );
// Generate the text using OpenAI API
function openai_generate_text_callback( $args ) {
$openai_api_key = get_option( 'openai_api_key' );
$prompt = $args['prompt'];
$title = $args['title'];
$model = isset( $args['model'] ) ? $args['model'] : 'text-davinci-003';
$max_length = isset( $args['max_length'] ) ? $args['max_length'] : 1024;
$temperature = isset( $args['temperature'] ) ? $args['temperature'] : 0.9;
$n = isset( $args['n'] ) ? $args['n'] : 1;
// Check if the API key is valid
if ( ! $openai_api_key ) {
    return;
}

// Prepare the request data
$data = array(
    'prompt' => $prompt,
    'max_tokens' => $max_length,
    'temperature' => $temperature,
    'n' => $n,
    'stop' => '\n'
);

// Convert the data to JSON
$json_data = wp_json_encode( $data );

// Send the request to OpenAI API
$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, "https://api.openai.com/v1/engines/$model/completions" );
curl_setopt( $ch, CURLOPT_POST, 1 );
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . $openai_api_key) );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
$response = curl_exec( $ch );
$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

// Check for errors
if ( $http_code !== 200 ) {
    return;
}

// Parse the response
$response_body = json_decode( $response, true );
$choices = $response_body['choices'];
$text = $choices[0]['text'];

// Create a new post with the generated text
$post_id = wp_insert_post( array(
    'post_title' => $title,
    'post_content' => $text,
    'post_status' => 'publish'
) );

// Check for errors
if ( is_wp_error( $post_id ) || $post_id === 0
) {
    return;
}
}

// Add the text generation event
add_action( 'openai_generate_text', 'openai_generate_text_callback' );

// Handle form submission on settings page
function openai_handle_form_submission() {
if ( isset( $_POST['openai_titles'] ) ) {
$titles = explode( "\n", sanitize_text_field( $_POST['openai_titles'] ) );
openai_schedule_text_generation( $titles );
echo '<div class="notice notice-success"><p>Article generation scheduled successfully!</p></div>';
}
}


<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://https://blueprintdigital.com/
 * @since      1.0.0
 *
 * @package    Jeanius
 * @subpackage Jeanius/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Jeanius
 * @subpackage Jeanius/public
 * @author     Blueprint Digital <development@blueprintdigital.com>
 */
class Jeanius_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version     = $version;

        /* existing enqueues */
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        /* NEW – register our front-end shortcode */
        add_shortcode( 'jeanius_assessment', array( $this, 'render_shortcode' ) );
    }

	/**
     * Callback that outputs the assessment root div
     */
    /**
 * Shortcode output for [jeanius_assessment]
 * Shows training screen once consent is granted.
 */
public function render_shortcode() {

	// Must be logged in
	if ( ! is_user_logged_in() ) {
		return '<p>Please log in to start your assessment.</p>';
	}

	// Get (or create) this user’s assessment post
	$post_id = \Jeanius\current_assessment_id();

	// Has the student granted consent yet?
	if ( ! get_field( 'consent_granted', $post_id ) ) {
		return '<p>You need to complete the consent form first.</p>
		        <a class="button" href="/jeanius-consent/">Open Consent Form</a>';
	}

	/* ───────── Training screen ───────── */
	ob_start(); ?>

	<div id="jeanius-training" style="max-width:700px;margin:auto;text-align:center">
		<h2>Your Jeanius Timeline Assessment</h2>

		<p>Watch this quick overview, then click **Get Started** to begin the
		   15-minute timeline exercise.</p>

		<iframe width="560" height="315"
		        src="https://www.youtube.com/embed/PMp_m6i37tQ"
		        title="YouTube video player" allowfullscreen></iframe><br><br>

		<button class="button button-primary"
		        onclick="location.href='/jeanius-assessment/wizard/'">
		       Get Started
		</button>
	</div>

	<?php
	return ob_get_clean();
}


	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Jeanius_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Jeanius_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/jeanius-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Jeanius_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Jeanius_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/jeanius-public.js', array( 'jquery' ), $this->version, false );

	}

	

}

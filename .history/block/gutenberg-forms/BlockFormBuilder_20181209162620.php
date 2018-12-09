<?php
/**
 * Created by PhpStorm.
 * User: essamamdani
 * Date: 15/10/2018
 * Time: 11:59 PM
 */


	class BlockFormBuilder {
		function __construct() {
			add_action( 'wp_ajax_nopriv_gutenberg_form_send_email', [ $this, 'gutenberg_form_send_email' ] );
			add_action( 'wp_ajax_gutenberg_form_send_email', [ $this, 'gutenberg_form_send_email' ] );
			add_action( 'init', [ $this, 'gutenberg_form_block_init' ] );
			add_filter( 'wp_mail_content_type',[$this,'email_set_content_type'] );
		}

		function email_set_content_type(){
			return "text/html";
		}
		public function init() {
			$this->attach_hooks();
		}

		public function attach_hooks() {
			add_action( 'the_posts', [ $this, 'gutenberg_form_enqueue_scripts' ], 0 );
		}

		public function gutenberg_form_block_init() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}
			$index_js   = 'dist/main.min.js';
			$child_js   = 'dist/child.min.js';
			$editor_css = 'dist/editor.min.css';
			$style_css  = 'dist/style.min.css';
			wp_register_script( 'gutenberg-forms-main-block-editor', plugins_url( $index_js, __FILE__ ), [
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-components',
				'wp-editor',
			], filemtime( GBF_BLOCK_DIR . "/$index_js" ) );
			wp_register_style( 'gutenberg-forms-style-block-editor', plugins_url( $editor_css, __FILE__ ), [], filemtime( GBF_BLOCK_DIR . "/$editor_css" ) );
			wp_register_style( 'gutenberg-forms-style-block', plugins_url( $style_css, __FILE__ ), [], filemtime( GBF_BLOCK_DIR . "/$style_css" ) );

			wp_register_script( 'gutenberg-forms-child-block-editor', plugins_url( $child_js, __FILE__ ), [
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-components',
				'wp-editor',
			], filemtime( GBF_BLOCK_DIR . "/$child_js" ) );


			register_block_type( 'gutenberg-forms/gutenberg-forms', array(
				'editor_script' => 'gutenberg-forms-main-block-editor',
				'editor_style'  => 'gutenberg-forms-style-block-editor',
				'style'         => 'gutenberg-forms-style-block',
			) );

			register_block_type( 'gutenberg-forms/button', array(
				'editor_script' => 'gutenberg-forms-child-block-editor'
			) );

			register_block_type( 'gutenberg-forms/input', [] );
			register_block_type( 'gutenberg-forms/textarea', [] );
			register_block_type( 'gutenberg-forms/captcha', [] );
		}

		public function gutenberg_form_enqueue_scripts( $post ) {


			$_SESSION['gutenberg_form_nonce'] = hash( "sha256", md5( random_int( 1, 4 ) + rand( 0, 999999 ) ) );

			$post_content = $post[0]->post_content;
			if ( is_singular() && preg_match( '/<!-- \/wp:gutenberg-forms\/gutenberg-forms -->/i', $post_content ) ) {
				wp_enqueue_script( 'ajax-script', plugin_dir_url(__FILE__) . 'dist/front.min.js', array( 'jquery' ), filemtime( GBF_BLOCK_DIR . 'dist/front.min.js' ), true );
				wp_localize_script( 'ajax-script', 'gutenbergformHelper', [
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'form_nonce' => $_SESSION['gutenberg_form_nonce'],
				] );
				if ( preg_match( '/data\-sitekey/i', $post_content ) ) {
					add_action( 'wp_head', [$this,'header_script'] );
				}
			}

			return $post;
		}

		function header_script() {
			echo "<script src='https://www.google.com/recaptcha/api.js'></script>";
		}

		public function gutenberg_form_send_email() {

			if ( !empty($_POST['formId']) ) {
				extract( $_POST );
				unset( $_SESSION['gutenberg_form_nonce'] );
				unset( $_POST['security'] );
				unset( $_POST['formId'] );
				unset( $_POST['action'] );
				unset( $_POST['security'] );
				unset( $_POST['post_slug'] );
				unset( $_POST['isAjax'] );
				$args        = array(
					'name'        => $_POST['post_slug'],
					'post_type'   => 'any',
					'numberposts' => 1
				);
				$query       = new WP_Query ( $args );
				$post        = $query->posts;
				$blocks      = gutenberg_parse_blocks( $post[0]->post_content );
				$catchBlocks = array_filter( $blocks, function ( $val ) use ( $formId ) {
					return $val->blockName === "gutenberg-forms/gutenberg-forms" && $val->attrs->formId === $formId;
				} );
				$toEmail = isset( $catchBlocks[0]->attrs->defaultEmail ) && ! $catchBlocks[0]->attrs->defaultEmail ? $catchBlocks[0]->attrs->emailAddress : get_option( 'admin_email' );
				$subject = isset( $catchBlocks[0]->attrs->subject ) ? $catchBlocks[0]->attrs->subject : "[" . get_bloginfo( 'name' ) . "] " . $post[0]->post_title;

				$not_human       = "Human verification incorrect.";
				$missing_content = "Please supply all information.";
				$message_unsent  = "Message was not sent. Try Again.";
				$message_sent    = "Thanks! Your message has been sent.";

				$remote_ip = $_SERVER["REMOTE_ADDR"];
				$reCaptcha = $_POST['g-recaptcha-response'];
				unset($_POST['g-recaptcha-response']);
				if ( isset( $reCaptcha ) ) {
					$response = isset( $reCaptcha ) ? esc_attr( $reCaptcha ) : '';
					$secretArr = array_filter( $catchBlocks[0]->innerBlocks, function ( $v ) {return $v->blockName === "gutenberg-forms/captcha" && $v->attrs->secretKey !== "";} );
					$secret    = array_values( $secretArr )[0]->attrs->secretKey;
					$request = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$response&remoteip=$remote_ip" );
					$response_body = wp_remote_retrieve_body( $request );

					$result = json_decode( $response_body, true );
					if ( !$result['success'] ) {
						wp_send_json( $not_human, 500 );
					}
				}
				$message   = "";
				$fromEmail = "no-reply@unknown.com";
				foreach ( $_POST as $key => $val ) {
					if ( filter_var( $val, FILTER_VALIDATE_EMAIL ) ) {
						$fromEmail = $val;
					}
					$message .= "<strong>" . strip_tags( str_replace("_"," ",$key) ) . "</strong>: " . strip_tags( $val ) . "<br/>";
				}
				$message .= "<br><br><hr>";
				$message .= "Time: " . date( "M d, Y, h:i a" ) . "<br>";
				$message .= "IP Address: $remote_ip<br>";
				$message .= "Contact Form URL: <a href='" . get_the_permalink($post[0]->ID) . "'>" . get_the_permalink($post[0]->ID) . "</a>";
				$message .= "<br>Sent by a verified <strong>" . get_bloginfo( 'name' ) . "</strong> user.";
				$headers = 'From: ' . $fromEmail . "\r\n" .
				           'Reply-To: ' . $fromEmail . "\r\n";
				wp_mail( $toEmail, $subject, $message, $headers ) ? wp_send_json( $message_sent, 200 ) : wp_send_json( $message_unsent, 500 );
			}
		}
	}

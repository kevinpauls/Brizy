<?php if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

class Brizy_Editor_User implements Brizy_Editor_SignatureInterface {

	const BRIZY_ATTACHMENT_HASH_KEY = 'brizy_attachment_hash';

	private static $instance;

	/**
	 * @var Brizy_Editor_API_AccessToken
	 */
	private $token;

	/**
	 * @var Brizy_Editor_Storage_Common
	 */
	private $common_storage;

	/**
	 * @var string
	 */
	private $platform_user_id;

	/**
	 * @var string
	 */
	private $platform_user_email;

	/**
	 * @var string
	 */
	private $platform_user_signature;


	/**
	 * @return Brizy_Editor_User
	 * @throws Brizy_Editor_Exceptions_ServiceUnavailable
	 * @throws Exception
	 */
	public static function get() {

		if ( self::$instance ) {
			return self::$instance;
		}

		if ( self::is_locked() ) {
			throw new Brizy_Editor_Exceptions_ServiceUnavailable(
				'User is in maintenance mode'
			);
		}
		$user = null;
		try {
			$user = new Brizy_Editor_User( Brizy_Editor_Storage_Common::instance() );
			$user->checkSignature();
		} catch ( Brizy_Editor_Exceptions_SignatureMismatch $e ) {
			self::clone_user( $user );
			$user = new Brizy_Editor_User( Brizy_Editor_Storage_Common::instance() );
		} catch ( Brizy_Editor_Exceptions_NotFound $e ) {
			self::create_user();
			$user = new Brizy_Editor_User( Brizy_Editor_Storage_Common::instance() );
		}

		return self::$instance = $user;
	}

	/**
	 * Brizy_Editor_User constructor.
	 *
	 * @param $common_storage
	 *
	 * @throws Exception
	 */
	protected function __construct( $common_storage ) {
		$this->common_storage = $common_storage;

		$this->platform_user_id        = $this->common_storage->get( 'platform_user_id' );
		$this->platform_user_email     = $this->common_storage->get( 'platform_user_email' );
		$this->platform_user_signature = $this->common_storage->get( 'platform_user_signature' );
		$this->token                   = $this->common_storage->get( 'access-token', false );

		if ( ! $this->token || $this->token->expired() ) {
			$this->auth();
			$this->token = $this->common_storage->get( 'access-token' );
		}
	}

	/**
	 * @throws Exception
	 */
	static protected function clone_user( Brizy_Editor_User $user ) {
		// we create a new user for now
		$platform = new Brizy_Editor_API_Platform();
		$platform->createUser( $user->getPlatformUserId() );
	}

	/**
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	static protected function create_user() {

		$platform = new Brizy_Editor_API_Platform();
		$platform->createUser();
	}

	/**
	 * @throws Exception
	 */
	public function checkSignature() {
		if ( Brizy_Editor_Signature::get() != $this->platform_user_signature ) {
			// clone required
			throw new Brizy_Editor_Exceptions_SignatureMismatch( 'Clone user required. Not implemented yet.' );
		}
	}


	/**
	 * @return string
	 */
	protected function random_email() {
		$uniqid = uniqid( 'brizy-' );

		return $uniqid . '@brizy.io';
	}


	/**
	 * @return $this
	 * @throws Exception
	 */
	public function login() {
		$this->auth();

		return $this;
	}

	/**
	 * @throws Exception
	 */
	public function auth() {
		try {
			self::lock_access();

			$credentials = Brizy_Editor_API_Platform::getCredentials();

			$auth_api = new Brizy_Editor_API_Auth( Brizy_Config::GATEWAY_URI, $credentials->client_id, $credentials->client_secret );
			$auth_api->clearTokenCache();
			$this->token = $auth_api->getToken( $this->platform_user_email );
			$this->common_storage->set( 'access-token', $this->token );

		} catch ( Exception $exception ) {
			self::unlock_access();
			throw $exception;
		}

		self::unlock_access();
	}


	/**
	 * @return void
	 */
	public static function logout() {
		Brizy_Editor_Storage_Common::instance()->delete( 'access-token' );
	}

	public function getCurrentUser() {
		return $this->get_client()->getUser();
	}

	/**
	 * @param null $from_project_id
	 *
	 * @return Brizy_Editor_API_Project
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Exceptions_NotFound
	 * @throws Brizy_Editor_Exceptions_UnsupportedPostType
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	public function create_project( $from_project_id = null ) {
		$project_data = $this->get_client()->create_project( $from_project_id );

		if ( $from_project_id ) {

			foreach ( $project_data['languages'] as $language ) {
				$pages = $language['pages'];
				if ( is_array( $pages ) && count( $pages ) > 0 ) {
					foreach ( $pages as $page ) {
						// get wordpress post by old brizy hash

						$wp_post = Brizy_Editor_Post::get_post_by_foreign_hash( $page['cloned_from'] );

						if ( ! $wp_post ) {
							continue;
						}

						$old_page = Brizy_Editor_Post::get( $wp_post );
						$new_post = new Brizy_Editor_Post( new Brizy_Editor_API_Page( $page ), $wp_post->ID );

						$new_post->set_compiled_html_body( $old_page->get_compiled_html_body() );
						$new_post->set_compiled_html_head( $old_page->get_compiled_html_head() );

						update_post_meta( $wp_post->ID, Brizy_Editor_Post::BRIZY_POST_SIGNATURE_KEY, Brizy_Editor_Signature::get() );
						update_post_meta( $wp_post->ID, Brizy_Editor_Post::BRIZY_POST_HASH_KEY, $new_post->get_api_page()->get_id() );

						$new_post->save();

					}
				}
			}
		}

		return new Brizy_Editor_API_Project( $project_data );
	}

	public function clone_pages( $page_ids, $project_target ) {
		return $this->get_client()->clone_pages( $page_ids, $project_target );
	}

	/**
	 * @param $project
	 * @param $page
	 *
	 * @return Brizy_Editor_API_Page
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	public function create_page( $project, $page ) {
		$page_response = $this->get_client()->create_page( $project->get_id(), $page );

		return new Brizy_Editor_API_Page( $page_response );

	}


	/**
	 * @param Brizy_Editor_API_Project $project
	 * @param Brizy_Editor_API_Page $page
	 *
	 * @return array|mixed|object
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized.
	 */
	public function delete_page( Brizy_Editor_API_Project $project, Brizy_Editor_API_Page $page ) {
		return $this->get_client()->delete_page( $project->get_id(), $page->get_id() );
	}

	/**
	 * @param Brizy_Editor_API_Project $project
	 * @param Brizy_Editor_API_Page $page
	 *
	 * @return array|mixed|object
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	public function update_page( Brizy_Editor_API_Project $project, Brizy_Editor_API_Page $page ) {
		$t = 0;

		return $this->get_client()
		            ->update_page(
			            $project->get_id(),
			            $page->get_id(),
			            $page
		            );
	}

	/**
	 * @param Brizy_Editor_API_Project $project
	 *
	 * @return array|mixed|object
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	public function update_project( Brizy_Editor_API_Project $project ) {
		return $this->get_client()->update_project( $project );
	}

	/**
	 * @param Brizy_Editor_API_Project $project
	 *
	 * @return array|mixed|object
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	public function get_project( Brizy_Editor_API_Project $project ) {
		return $this->get_client()->get_project( $project );
	}


	/**
	 * @param Brizy_Editor_Project $project
	 * @param Brizy_Editor_Post $post
	 *
	 * @return Brizy_Editor_CompiledHtml
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 * @throws Exception
	 */
	public function compile_page( Brizy_Editor_Project $project, Brizy_Editor_Post $post ) {
		$api_project = $project->get_api_project();
		$api_page    = $post->get_api_page();
		$url_builder = new Brizy_Editor_UrlBuilder( $project, $post );

		$config = Brizy_Editor_Editor_Editor::get( $project, $post )->config();

		if ( ! is_preview() ) {
			$config['urls']['static'] = $url_builder->upload_url( $url_builder->editor_asset_path() );
			$config['urls']['image']  = $url_builder->upload_url( $url_builder->media_asset_path() );
		}

		$res = $this->get_client()->compile_page( $api_project, $api_page, $config );

		$content = trim( $res['html'] );

		return new Brizy_Editor_CompiledHtml( $content );
	}

	/**
	 * @param Brizy_Editor_Project $project
	 * @param $attachment_id
	 *
	 * @return mixed|null
	 * @throws Brizy_Editor_API_Exceptions_Exception
	 * @throws Brizy_Editor_Exceptions_NotFound
	 * @throws Brizy_Editor_Http_Exceptions_BadRequest
	 * @throws Brizy_Editor_Http_Exceptions_ResponseException
	 * @throws Brizy_Editor_Http_Exceptions_ResponseNotFound
	 * @throws Brizy_Editor_Http_Exceptions_ResponseUnauthorized
	 */
	public function get_media_id( Brizy_Editor_Project $project, $attachment_id ) {

		$brizy_editor_storage_post = Brizy_Editor_Storage_Post::instance( $attachment_id );
		$hash_name                 = null;
		try {
			$hash_name = $brizy_editor_storage_post->get( self::BRIZY_ATTACHMENT_HASH_KEY );
		} catch ( Brizy_Editor_Exceptions_NotFound $exception ) {

			$response = $this
				->get_client()
				->add_media( $project->get_id(), $this->image_to_base64( $attachment_id ) );

			$brizy_editor_storage_post->set( self::BRIZY_ATTACHMENT_HASH_KEY, $response['name'] );

			$hash_name = $response['name'];
		}

		return $hash_name;
	}

	protected function get_token() {
		return $this->token;
	}

	protected function get_client() {
		return new Brizy_Editor_API_Client( $this->get_token() );
	}

	protected function image_to_base64( $attachment_id ) {
		$path = get_attached_file( $attachment_id, true );

		if ( ! $path ) {
			throw new Brizy_Editor_Exceptions_NotFound( "Attachment $attachment_id cannot be found" );
		}

		$data = file_get_contents( $path );

		return base64_encode( $data );
	}

	protected static function lock_access() {
		set_transient( self::lock_key(), 1, 30 );
	}

	protected static function unlock_access() {
		delete_transient( self::lock_key() );
	}

	protected static function lock_key() {
		return brizy()->get_slug() . '-user-maintenance-enabled';
	}

	protected static function is_locked() {
		return (bool) get_transient( self::lock_key() );
	}

	/**
	 * @return string
	 */
	public function getPlatformUserId() {
		return $this->platform_user_id;
	}

	/**
	 * @return string
	 */
	public function getPlatformUserEmail() {
		return $this->platform_user_email;
	}

	/**
	 * @return string
	 */
	public function getPlatformUserSignature() {
		return $this->platform_user_signature;
	}


}

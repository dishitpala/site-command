<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Migration\SiteContainers;
use EE\RevertableStepProcessor;
use EE\Model\Site;

class RenewSsl extends Base {

	private $sites;
	/** @var RevertableStepProcessor $rsp Keeps track of migration state. Reverts on error */
	private static $rsp;

	public function __construct() {

		parent::__construct();
		$this->sites = Site::all();
		if ( $this->is_first_execution || ! $this->sites ) {
			$this->skip_this_migration = true;
		}
	}

	/**
	 * Execute php config updates.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping renew-ssl migration as it is not needed.' );

			return;
		}
		self::$rsp = new RevertableStepProcessor();

		foreach ( $this->sites as $site ) {

			EE::debug( "Found site: $site->site_url of type: $site->site_type" );

			if ( 'le' !== $site->site_ssl || $site->site_ssl_wildcard ) {
				continue;
			}

			EE::debug( "Starting ssl-renew update for: $site->site_url" );

			$ssl_redirect_updater = new RenewSslExtendor();
			$ssl_redirect_updater->renew_ssl_migration( $site->site_url );
		}

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to run renew-ssl migrations.' );
		}
	}

	/**
	 * Not needed.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {
	}
}

class RenewSslExtendor extends EE\Site\Type\EE_Site_Command {
	public function renew_ssl_migration( $site_url ) {
		$this->site_data = \EE\Site\Utils\get_site_info( [ $site_url ], false, false, true );
		$postfix_exists  = \EE_DOCKER::service_exists( 'postfix', $this->site_data['site_fs_path'] );
		if ( $this->site_data['site_ssl'] ) {
			$this->site_data['site_ssl'] = 'le';
		}
		$containers_to_start = $postfix_exists ? [ 'nginx', 'postfix' ] : [ 'nginx' ];
		$this->www_ssl_wrapper( $containers_to_start, false, true, true );
	}
	public function create( $args, $assoc_args ) {}
	protected function rollback() {}
	public function dump_docker_compose_yml( $additional_filters = [] ) {}
}

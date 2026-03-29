<?php

namespace UniversalCPTMigrator\Infrastructure;

class Storage {
	private $base_path;
	private $base_url;

	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->base_path = trailingslashit( $upload_dir['basedir'] ) . 'u-cpt-mgr';
		$this->base_url  = trailingslashit( $upload_dir['baseurl'] ) . 'u-cpt-mgr';
	}

	public function setup_directories() {
		$dirs = [
			$this->base_path,
			$this->base_path . '/exports',
			$this->base_path . '/imports',
			$this->base_path . '/temp',
			$this->base_path . '/logs',
		];

		foreach ( $dirs as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
				// Security: Add index.php for zero directory listing
				file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
				// Security: Add .htaccess to block all direct web access
				file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all" );
			}
		}
	}

	public function get_path( $sub = '' ) {
		return $sub ? trailingslashit( $this->base_path ) . $sub : $this->base_path;
	}

	public function get_url( $sub = '' ) {
		return $sub ? trailingslashit( $this->base_url ) . $sub : $this->base_url;
	}

	public function put_contents( $sub, $contents ) {
		$path = $this->get_path( $sub );
		$dir  = dirname( $path );

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return false !== file_put_contents( $path, $contents );
	}

	public function read_contents( $sub ) {
		$path = $this->get_path( $sub );

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		return file_get_contents( $path );
	}

	public function list_files( $sub = '', $extension = '' ) {
		$path  = $this->get_path( $sub );
		$files = [];

		if ( ! file_exists( $path ) || ! is_dir( $path ) ) {
			return $files;
		}

		foreach ( scandir( $path ) as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$full_path = trailingslashit( $path ) . $file;
			if ( ! is_file( $full_path ) ) {
				continue;
			}

			if ( $extension && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) !== strtolower( $extension ) ) {
				continue;
			}

			$files[] = [
				'name'     => $file,
				'path'     => $full_path,
				'size'     => filesize( $full_path ),
				'modified' => filemtime( $full_path ),
			];
		}

		usort(
			$files,
			static function( $a, $b ) {
				return $b['modified'] <=> $a['modified'];
			}
		);

		return $files;
	}

	public function purge_old_files( $sub, $max_age_days ) {
		$deleted   = 0;
		$threshold = time() - ( DAY_IN_SECONDS * max( 1, absint( $max_age_days ) ) );

		foreach ( $this->list_files( $sub ) as $file ) {
			if ( $file['modified'] < $threshold && @unlink( $file['path'] ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}

	public function purge_old_directories( $sub, $max_age_days ) {
		$deleted   = 0;
		$threshold = time() - ( DAY_IN_SECONDS * max( 1, absint( $max_age_days ) ) );
		$path      = $this->get_path( $sub );

		if ( ! file_exists( $path ) || ! is_dir( $path ) ) {
			return 0;
		}

		foreach ( scandir( $path ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$full_path = trailingslashit( $path ) . $entry;
			if ( ! is_dir( $full_path ) ) {
				continue;
			}

			if ( filemtime( $full_path ) < $threshold ) {
				$this->delete_directory( $full_path );
				$deleted++;
			}
		}

		return $deleted;
	}

	public function delete_relative( $sub ) {
		$path = $this->get_path( $sub );
		if ( is_dir( $path ) ) {
			$this->delete_directory( $path );
			return true;
		}

		if ( is_file( $path ) ) {
			return @unlink( $path );
		}

		return false;
	}

	public function delete_all() {
		$this->delete_directory( $this->base_path );
	}

	private function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}

	public function clear_temp() {
		$temp_path = $this->get_path( 'temp' );
		$files = glob( $temp_path . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
}

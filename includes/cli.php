<?php
/**
 * WP-CLI command.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage places.
 */
class Tufte_Places_CLI {

	/**
	 * Sync post places from the photos each post uses.
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Sync one post instead of every post.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function sync( $args, $assoc_args ): void {
		if ( isset( $assoc_args['post'] ) ) {
			$ids = array( (int) $assoc_args['post'] );
		} else {
			$ids = get_posts(
				array(
					'post_type'      => tufte_places_synced_types(),
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
		}

		foreach ( $ids as $id ) {
			tufte_places_sync_post( (int) $id );
		}

		WP_CLI::success( sprintf( 'Synced %d posts.', count( $ids ) ) );
	}
}

WP_CLI::add_command( 'tufte-places', 'Tufte_Places_CLI' );

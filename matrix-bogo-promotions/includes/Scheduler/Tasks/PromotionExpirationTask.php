<?php
/**
 * Promotion Expiration Task – sets a rule to inactive at its end time.
 *
 * @package MatrixBogo\Scheduler\Tasks
 */

declare( strict_types=1 );

namespace MatrixBogo\Scheduler\Tasks;

use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromotionExpirationTask
 */
final class PromotionExpirationTask {

	public function run( int $rule_id ): void {
		$repo = new RulesRepository();
		$rule = $repo->find( $rule_id );

		if ( ! $rule ) {
			Logger::warning( 'scheduler.expire', 'Rule not found during expiration task.', [], $rule_id );
			return;
		}

		if ( 'active' !== $rule['status'] ) {
			return;
		}

		$repo->update( $rule_id, [ 'status' => 'inactive' ] );
		$repo->flush_cache();

		Logger::info( 'scheduler.expire', 'Rule expired by scheduler.', [], $rule_id );
	}
}

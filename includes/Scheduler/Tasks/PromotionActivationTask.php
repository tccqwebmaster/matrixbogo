<?php
/**
 * Promotion Activation Task – sets a rule to active at its start time.
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
 * Class PromotionActivationTask
 */
final class PromotionActivationTask {

	public function run( int $rule_id ): void {
		$repo = new RulesRepository();
		$rule = $repo->find( $rule_id );

		if ( ! $rule ) {
			Logger::warning( 'scheduler.activate', 'Rule not found during activation task.', [], $rule_id );
			return;
		}

		if ( 'scheduled' !== $rule['status'] ) {
			return;
		}

		$repo->update( $rule_id, [ 'status' => 'active' ] );
		$repo->flush_cache();

		Logger::info( 'scheduler.activate', 'Rule activated by scheduler.', [], $rule_id );
	}
}

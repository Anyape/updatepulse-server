<?php

namespace Anyape\UpdatePulse\Server\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_Error;

/**
 * Scheduler class
 *
 * @since 1.0.0
 */
class Scheduler {
	/**
	 * Instance
	 *
	 * @var Scheduler|null
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks.
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'action_scheduler_init', array( $this, 'action_scheduler_init' ), 10, 0 );
			add_action( 'init', array( $this, 'init' ), 5, 0 );
		}
	}

	/**
	 * Get instance
	 *
	 * Retrieve or create the Scheduler singleton instance.
	 *
	 * @return Scheduler The scheduler instance.
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Magic method handler
	 *
	 * Routes method calls to either ActionScheduler functions or native WordPress functions.
	 *
	 * @param string $name The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed|WP_Error The result of the method call or error if method doesn't exist.
	 * @since 1.0.0
	 */
	public function __call( $name, $arguments ) {

		if ( ! method_exists( $this, $name ) ) {
			return new WP_Error(
				'invalid_method',
				sprintf(
					/* translators: 1: full cass name ; 2: method name */
					__( '%1$s: Method %2$s does not exist.', 'updatepulse-server' ),
					esc_html( __CLASS__ ),
					esc_html( $name )
				)
			);
		}

		if ( class_exists( 'ActionScheduler', false ) ) {
			$name = 'as_' . $name;

			return $name( ...$arguments );
		}

		return $this->$name( ...$arguments );
	}

	/**
	 * Action scheduler initialization
	 *
	 * Fires when the Action Scheduler is initialized.
	 *
	 * @since 1.0.0
	 */
	public function action_scheduler_init() {
		do_action( 'upserv_scheduler_init' );
	}

	/**
	 * Initialize
	 *
	 * Handles plugin initialization logic.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		if ( ! class_exists( 'ActionScheduler', false ) ) {
			do_action( 'upserv_scheduler_init' );
		}
	}

	/**
	 * Schedule single action
	 *
	 * Schedule a one-time WordPress cron event for the fallback scheduler.
	 *
	 * @param int    $timestamp When the action should run (Unix timestamp).
	 * @param string $hook The hook to execute.
	 * @param array  $args Arguments to pass to the hook's callback.
	 * @param string $group The group to assign this action to.
	 * @param bool   $unique Whether to ensure this action is unique.
	 * @param int    $priority The priority of the action.
	 * @return bool Whether the event was scheduled.
	 * @since 1.0.0
	 */
	protected function schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if ( $unique ) {
			$this->unschedule_all_actions( $hook, $args, $group );
		}

		return wp_schedule_single_event( $timestamp, $hook, $args );
	}

	/**
	 * Schedule recurring action
	 *
	 * Schedule a repeating WordPress cron event for the fallback scheduler.
	 *
	 * @param int    $timestamp When the action should first run (Unix timestamp).
	 * @param int    $interval_in_seconds How long to wait between runs.
	 * @param string $hook The hook to execute.
	 * @param array  $args Arguments to pass to the hook's callback.
	 * @param string $group The group to assign this action to.
	 * @param bool   $unique Whether to ensure this action is unique.
	 * @param int    $priority The priority of the action.
	 * @return bool Whether the event was scheduled.
	 * @since 1.0.0
	 */
	protected function schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if ( $unique ) {
			$this->unschedule_all_actions( $hook, $args, $group );
		}

		$schedules        = wp_get_schedules();
		$intervals        = array_map(
			function ( $schedule ) {
				return $schedule['interval'];
			},
			$schedules
		);
		$closest_interval = null;
		$smallest_diff    = PHP_INT_MAX;

		foreach ( $intervals as $interval_name => $interval_value ) {
			$diff = abs( $interval_value - $interval_in_seconds );

			if ( $diff < $smallest_diff ) {
				$smallest_diff    = $diff;
				$closest_interval = $interval_name;
			}
		}

		$interval = $closest_interval ? $closest_interval : 'hourly';

		return wp_schedule_event( $timestamp, $interval, $hook, $args );
	}

	/**
	 * Unschedule all actions
	 *
	 * Cancel all scheduled instances of a specific action.
	 *
	 * @param string $hook The action hook to unschedule.
	 * @param array  $args Args matching those of the action to unschedule.
	 * @param string $group The group to which the action belongs.
	 * @return void
	 * @since 1.0.0
	 */
	protected function unschedule_all_actions( $hook, $args = array(), $group = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$timestamp = wp_next_scheduled( $hook, $args );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook, $args );
			$timestamp = wp_next_scheduled( $hook, $args );
		}
	}

	/**
	 * Get next scheduled action
	 *
	 * Retrieve the next timestamp for a scheduled action.
	 *
	 * @param string $hook The hook to check.
	 * @param array  $args Args matching those of the action to check.
	 * @param string $group The group to which the action belongs.
	 * @return int|false The timestamp for the next occurrence or false if not scheduled.
	 * @since 1.0.0
	 */
	protected function next_scheduled_action( $hook, $args = array(), $group = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return wp_next_scheduled( $hook, $args );
	}

	/**
	 * Check if action is scheduled
	 *
	 * Determine whether an action is currently scheduled.
	 *
	 * @param string $hook The hook to check.
	 * @param array  $args Args matching those of the action to check.
	 * @param string $group The group to which the action belongs.
	 * @return bool Whether the action is scheduled.
	 * @since 1.0.0
	 */
	protected function has_scheduled_action( $hook, $args = array(), $group = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return (bool) wp_next_scheduled( $hook, $args );
	}
}

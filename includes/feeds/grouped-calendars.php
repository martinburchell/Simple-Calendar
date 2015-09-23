<?php
/**
 * Grouped Calendars Feed
 *
 * @package SimpleCalendar/Feeds
 */
namespace SimpleCalendar\Feeds;

use SimpleCalendar\Abstracts\Calendar;
use SimpleCalendar\Abstracts\Feed;
use SimpleCalendar\Feeds\Admin\Grouped_Calendars_Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grouped calendars feed.
 *
 * Feed made of multiple calendar feeds combined together.
 */
class Grouped_Calendars extends Feed {

	/**
	 * Feed ids to get events from.
	 *
	 * @access public
	 * @var array
	 */
	public $ids = array();

	/**
	 * Set properties.
	 *
	 * @param string|Calendar $calendar
	 */
	public function __construct( $calendar = '' ) {

		parent::__construct( $calendar );

		$this->type = 'grouped-calendars';
		$this->name = __( 'Grouped Calendars', 'google-calendar-events' );

		if ( $this->calendar_id > 0 ) {
			$this->set_source();
			$this->events = $this->get_events();
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			new Grouped_Calendars_Admin( $this );
		}
	}

	/**
	 * Set source.
	 *
	 * @param array $ids Array of calendar ids.
	 */
	public function set_source( $ids = array() ) {

		$source = get_post_meta( $this->calendar_id, '_grouped_calendars_source', true );

		if ( 'ids' == $source ) {

			if ( empty( $ids ) ) {
				$ids = get_post_meta( $this->calendar_id, '_grouped_calendars_ids', true );
			}

			$this->ids = ! empty( $ids ) && is_array( $ids ) ? array_map( 'absint', $ids ) : array();

		} elseif ( 'category' == $source ) {

			$categories = get_post_meta( $this->calendar_id, '_grouped_calendars_category', true );

			if ( $categories && is_array( $categories ) ) {

				$tax_query = array(
					'taxonomy' => 'events_feed_category',
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', $categories ),
				);

				$calendars = get_posts( array(
					'post_type' => 'calendar',
					'tax_query' => $tax_query,
					'nopaging'  => true,
					'fields'    => 'ids',
				) );

				$this->ids = ! empty( $calendars ) && is_array( $calendars ) ? $calendars : array();
			}

		}
	}

	/**
	 * Get events from multiple calendars.
	 *
	 * @return array
	 */
	public function get_events() {

		$ids       = $this->ids;
		$events    = get_transient( '_simple-calendar_feed_id_' . strval ( $this->calendar_id ) . '_' . $this->type );

		if ( empty( $events ) && ! empty( $ids ) && is_array( $ids ) ) {

			$events = array();

			foreach ( $ids as $cal_id ) {

				$calendar = simcal_get_calendar( intval( $cal_id ) );

				if ( $calendar instanceof Calendar ) {
					$events = is_array( $calendar->events ) ? $events + $calendar->events : $events;
				}

			}

			if ( ! empty( $events ) ) {

				// Trim events to set the earliest one as specified in feed settings.
				$earliest_event = intval( $this->time_min );
				if ( $earliest_event > 0 ) {
					$events = $this->array_filter_key( $events, array( $this, 'filter_events_before' ) );
				}

				// Trim events to set the latest one as specified in feed settings.
				$latest_event = intval( $this->time_max );
				if ( $latest_event > 0 ) {
					$events = $this->array_filter_key( $events, array( $this, 'filter_events_after' ) );
				}

				set_transient(
					'_simple-calendar_feed_id_' . strval( $this->calendar_id ) . '_' . $this->type,
					$events,
					absint( $this->cache )
				);
			}

		}

		return $events;
	}

	/**
	 * Array filter key.
	 *
	 * `array_filter` does not allow to parse an associative array keys before PHP 5.6.
	 *
	 * @param  array        $array
	 * @param  array|string $callback
	 *
	 * @return array
	 */
	private function array_filter_key( array $array, $callback ) {
		$matched_keys = array_filter( array_keys( $array ), $callback );
		return array_intersect_key( $array, array_flip( $matched_keys ) );
	}

	/**
	 * Array filter callback.
	 *
	 * @param  int $event Timestamp.
	 *
	 * @return bool
	 */
	private function filter_events_before( $event ) {
		if ( $this->time_min !== 0 ) {
			return intval( $event ) > intval( $this->time_min );
		}
		return true;
	}

	/**
	 * Array filter callback.
	 *
	 * @param  int $event Timestamp.
	 *
	 * @return bool
	 */
	private function filter_events_after( $event ) {
		if ( $this->time_max !== 0 ) {
			return intval( $event ) < intval( $this->time_max );
		}
		return true;
	}

}

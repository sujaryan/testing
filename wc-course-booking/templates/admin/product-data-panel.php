<?php
/**
 * 'Course booking' product data panel rendered under the Product edit screen.
 *
 * @var int   $product_id
 * @var array $settings
 */
defined( 'ABSPATH' ) || exit;

$weekdays = array(
	0 => __( 'Sunday', 'wc-course-booking' ),
	1 => __( 'Monday', 'wc-course-booking' ),
	2 => __( 'Tuesday', 'wc-course-booking' ),
	3 => __( 'Wednesday', 'wc-course-booking' ),
	4 => __( 'Thursday', 'wc-course-booking' ),
	5 => __( 'Friday', 'wc-course-booking' ),
	6 => __( 'Saturday', 'wc-course-booking' ),
);
?>
<div id="wccb_course_data" class="panel woocommerce_options_panel show_if_course_booking">
	<?php wp_nonce_field( 'wccb_save_course_settings', 'wccb_nonce' ); ?>

	<div class="options_group">
		<p class="form-field">
			<label for="wccb_duration"><?php esc_html_e( 'Session duration (minutes)', 'wc-course-booking' ); ?></label>
			<input type="number" id="wccb_duration" name="wccb_duration" min="5" step="5" value="<?php echo esc_attr( $settings['duration'] ); ?>" />
		</p>
		<p class="form-field">
			<label for="wccb_increment"><?php esc_html_e( 'Slot increment (minutes)', 'wc-course-booking' ); ?></label>
			<input type="number" id="wccb_increment" name="wccb_increment" min="5" step="5" value="<?php echo esc_attr( $settings['increment'] ); ?>" />
			<span class="description"><?php esc_html_e( 'How far apart consecutive start times are.', 'wc-course-booking' ); ?></span>
		</p>
		<p class="form-field">
			<label for="wccb_buffer"><?php esc_html_e( 'Buffer between sessions (minutes)', 'wc-course-booking' ); ?></label>
			<input type="number" id="wccb_buffer" name="wccb_buffer" min="0" step="5" value="<?php echo esc_attr( $settings['buffer'] ); ?>" />
		</p>
		<p class="form-field">
			<label for="wccb_min_notice"><?php esc_html_e( 'Minimum notice (minutes)', 'wc-course-booking' ); ?></label>
			<input type="number" id="wccb_min_notice" name="wccb_min_notice" min="0" step="5" value="<?php echo esc_attr( $settings['min_notice'] ); ?>" />
		</p>
		<p class="form-field">
			<label for="wccb_window_days"><?php esc_html_e( 'Booking window (days ahead)', 'wc-course-booking' ); ?></label>
			<input type="number" id="wccb_window_days" name="wccb_window_days" min="1" value="<?php echo esc_attr( $settings['window_days'] ); ?>" />
		</p>
		<p class="form-field">
			<label for="wccb_capacity"><?php esc_html_e( 'Bookings per time slot', 'wc-course-booking' ); ?></label>
			<input type="number" id="wccb_capacity" name="wccb_capacity" min="1" value="<?php echo esc_attr( $settings['capacity'] ); ?>" />
			<span class="description"><?php esc_html_e( 'Set to 1 for Calendly-style 1:1 sessions, or a higher number for group classes. Remaining spots appear next to each time on the student-facing picker.', 'wc-course-booking' ); ?></span>
		</p>
		<p class="form-field">
			<label for="wccb_timezone"><?php esc_html_e( 'Course timezone', 'wc-course-booking' ); ?></label>
			<select id="wccb_timezone" name="wccb_timezone">
				<?php foreach ( timezone_identifiers_list() as $tz ) : ?>
					<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $settings['timezone'], $tz ); ?>><?php echo esc_html( $tz ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="form-field">
			<label for="wccb_instructor_id"><?php esc_html_e( 'Instructor', 'wc-course-booking' ); ?></label>
			<?php
			wp_dropdown_users(
				array(
					'name'             => 'wccb_instructor_id',
					'selected'         => $settings['instructor_id'],
					'show_option_none' => __( '— None —', 'wc-course-booking' ),
					'role__in'         => array( 'administrator', 'shop_manager', 'editor', 'author' ),
				)
			);
			?>
		</p>
		<p class="form-field">
			<label for="wccb_meeting_url"><?php esc_html_e( 'Default meeting URL', 'wc-course-booking' ); ?></label>
			<input type="url" id="wccb_meeting_url" name="wccb_meeting_url" value="<?php echo esc_attr( $settings['meeting_url'] ); ?>" placeholder="https://meet.example.com/..." />
		</p>
	</div>

	<div class="options_group">
		<h4 style="padding-left:12px;"><?php esc_html_e( 'Weekly availability', 'wc-course-booking' ); ?></h4>
		<table class="widefat striped wccb-schedule-table" style="margin: 10px 12px; width: calc(100% - 24px);">
			<thead>
				<tr>
					<th style="width: 110px;"><?php esc_html_e( 'Day', 'wc-course-booking' ); ?></th>
					<th style="width: 80px;"><?php esc_html_e( 'Enabled', 'wc-course-booking' ); ?></th>
					<th><?php esc_html_e( 'Start', 'wc-course-booking' ); ?></th>
					<th><?php esc_html_e( 'End', 'wc-course-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $weekdays as $dow => $label ) :
					$day     = $settings['schedule'][ $dow ] ?? array( 'enabled' => false, 'ranges' => array() );
					$enabled = ! empty( $day['enabled'] );
					$range   = ! empty( $day['ranges'][0] ) ? $day['ranges'][0] : array( 'start' => '09:00', 'end' => '17:00' );
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td>
							<input type="checkbox" name="wccb_schedule[<?php echo (int) $dow; ?>][enabled]" value="1" <?php checked( $enabled ); ?> />
						</td>
						<td>
							<input type="time" name="wccb_schedule[<?php echo (int) $dow; ?>][ranges][0][start]" value="<?php echo esc_attr( $range['start'] ); ?>" />
						</td>
						<td>
							<input type="time" name="wccb_schedule[<?php echo (int) $dow; ?>][ranges][0][end]" value="<?php echo esc_attr( $range['end'] ); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description" style="padding-left:12px;">
			<?php esc_html_e( 'One working window per day. For multi-range support or specific-date overrides, edit the meta directly or extend the plugin.', 'wc-course-booking' ); ?>
		</p>
	</div>

	<div class="options_group">
		<h4 style="padding-left:12px;"><?php esc_html_e( 'Date overrides', 'wc-course-booking' ); ?></h4>
		<p class="description" style="padding-left:12px;">
			<?php esc_html_e( 'Mark specific dates as closed (holidays) or set a custom window.', 'wc-course-booking' ); ?>
		</p>
		<table class="widefat striped wccb-overrides-table" style="margin: 10px 12px; width: calc(100% - 24px);">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'wc-course-booking' ); ?></th>
					<th><?php esc_html_e( 'Type', 'wc-course-booking' ); ?></th>
					<th><?php esc_html_e( 'Start', 'wc-course-booking' ); ?></th>
					<th><?php esc_html_e( 'End', 'wc-course-booking' ); ?></th>
				</tr>
			</thead>
			<tbody id="wccb-overrides-body">
				<?php
				$rows = array_values( $settings['overrides'] );
				if ( empty( $rows ) ) {
					$rows = array( array( 'date' => '', 'type' => 'closed', 'ranges' => array() ) );
				}
				$i = 0;
				foreach ( $settings['overrides'] as $date => $ov ) :
					$r = $ov['ranges'][0] ?? array( 'start' => '09:00', 'end' => '17:00' );
					?>
					<tr>
						<td><input type="date" name="wccb_overrides[<?php echo (int) $i; ?>][date]" value="<?php echo esc_attr( $date ); ?>" /></td>
						<td>
							<select name="wccb_overrides[<?php echo (int) $i; ?>][type]">
								<option value="closed" <?php selected( $ov['type'], 'closed' ); ?>><?php esc_html_e( 'Closed', 'wc-course-booking' ); ?></option>
								<option value="custom" <?php selected( $ov['type'], 'custom' ); ?>><?php esc_html_e( 'Custom hours', 'wc-course-booking' ); ?></option>
							</select>
						</td>
						<td><input type="time" name="wccb_overrides[<?php echo (int) $i; ?>][ranges][0][start]" value="<?php echo esc_attr( $r['start'] ); ?>" /></td>
						<td><input type="time" name="wccb_overrides[<?php echo (int) $i; ?>][ranges][0][end]" value="<?php echo esc_attr( $r['end'] ); ?>" /></td>
					</tr>
					<?php
					$i++;
				endforeach;
				// Always give them an empty row to extend.
				?>
				<tr>
					<td><input type="date" name="wccb_overrides[<?php echo (int) $i; ?>][date]" value="" /></td>
					<td>
						<select name="wccb_overrides[<?php echo (int) $i; ?>][type]">
							<option value="closed"><?php esc_html_e( 'Closed', 'wc-course-booking' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Custom hours', 'wc-course-booking' ); ?></option>
						</select>
					</td>
					<td><input type="time" name="wccb_overrides[<?php echo (int) $i; ?>][ranges][0][start]" value="09:00" /></td>
					<td><input type="time" name="wccb_overrides[<?php echo (int) $i; ?>][ranges][0][end]" value="17:00" /></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

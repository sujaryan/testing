/**
 * WC Course Booking — frontend calendar & slot picker.
 *
 * Lightweight, no-framework implementation. Renders a month calendar, asks
 * the server for slots per month, lets the user pick a date+time, and wires
 * the selection into the product add-to-cart form via hidden inputs.
 */
(function ($) {
	'use strict';

	if (typeof wccbData === 'undefined') {
		return;
	}

	function pad(n) {
		return n < 10 ? '0' + n : '' + n;
	}

	function formatMonthTitle(year, month) {
		return wccbData.i18n.months[month] + ' ' + year;
	}

	function BookingWidget($root) {
		this.$root = $root;
		this.productId = parseInt($root.data('product-id'), 10);
		this.duration = parseInt($root.data('duration'), 10);
		this.now = new Date();
		this.view = { year: this.now.getFullYear(), month: this.now.getMonth() };
		this.slotsByDate = {};
		this.selectedDate = null;
		this.selectedSlot = null;
		this.holdExpiresAt = 0;
		this.$form = $root.closest('form.cart');

		this.$calendar = $root.find('.wccb-calendar');
		this.$grid = $root.find('.wccb-calendar__grid');
		this.$weekdays = $root.find('.wccb-calendar__weekdays');
		this.$title = $root.find('.wccb-calendar__title');
		this.$slotsList = $root.find('.wccb-slots__list');
		this.$status = $root.find('.wccb-slots__status');
		this.$selectedDateLabel = $root.find('.wccb-slots__selected-date');

		this.renderWeekdays();
		this.bind();
		this.fetchMonth();
		this.disableSubmitUntilPicked();
	}

	BookingWidget.prototype.bind = function () {
		var self = this;
		this.$root.on('click', '.wccb-calendar__prev', function () { self.changeMonth(-1); });
		this.$root.on('click', '.wccb-calendar__next', function () { self.changeMonth(1); });

		this.$root.on('click', '.wccb-calendar__day.is-available', function () {
			var date = $(this).data('date');
			self.selectDate(date);
		});

		this.$root.on('click', '.wccb-slot:not(.is-disabled)', function () {
			var $slot = $(this);
			self.selectSlot($slot.data('slot'), $slot);
		});

		if (this.$form.length) {
			this.$form.on('submit', function (e) {
				if (!self.selectedSlot) {
					e.preventDefault();
					self.setStatus(wccbData.i18n.selectSlot, 'error');
				}
			});
		}
	};

	BookingWidget.prototype.disableSubmitUntilPicked = function () {
		if (!this.$form.length) { return; }
		var $btn = this.$form.find('button[type="submit"], input[type="submit"]');
		$btn.prop('disabled', true).addClass('disabled');
	};

	BookingWidget.prototype.enableSubmit = function () {
		if (!this.$form.length) { return; }
		var $btn = this.$form.find('button[type="submit"], input[type="submit"]');
		$btn.prop('disabled', false).removeClass('disabled');
	};

	BookingWidget.prototype.renderWeekdays = function () {
		var start = wccbData.weekStartsOn || 0;
		var html = '';
		for (var i = 0; i < 7; i++) {
			var idx = (start + i) % 7;
			html += '<div>' + wccbData.i18n.weekdaysShort[idx] + '</div>';
		}
		this.$weekdays.html(html);
	};

	BookingWidget.prototype.changeMonth = function (delta) {
		var m = this.view.month + delta;
		var y = this.view.year;
		if (m < 0) { m = 11; y -= 1; }
		if (m > 11) { m = 0; y += 1; }
		this.view = { year: y, month: m };
		this.fetchMonth();
	};

	BookingWidget.prototype.fetchMonth = function () {
		var self = this;
		var monthStr = this.view.year + '-' + pad(this.view.month + 1);
		this.$title.text(formatMonthTitle(this.view.year, this.view.month));
		this.renderCalendar({});
		this.setStatus(wccbData.i18n.loading);

		$.getJSON(wccbData.ajaxUrl, {
			action: 'wccb_get_slots',
			nonce: wccbData.nonce,
			product_id: this.productId,
			month: monthStr
		}).done(function (resp) {
			if (resp && resp.success) {
				self.slotsByDate = resp.data.slots || {};
				self.renderCalendar(self.slotsByDate);
				self.setStatus('');
			} else {
				self.setStatus((resp && resp.data && resp.data.message) || 'Error loading slots.', 'error');
			}
		}).fail(function () {
			self.setStatus('Error loading slots.', 'error');
		});
	};

	BookingWidget.prototype.renderCalendar = function (slotsByDate) {
		var year = this.view.year;
		var month = this.view.month;
		var firstOfMonth = new Date(year, month, 1);
		var startDow = firstOfMonth.getDay();
		var weekStart = wccbData.weekStartsOn || 0;
		var leading = (startDow - weekStart + 7) % 7;
		var daysInMonth = new Date(year, month + 1, 0).getDate();

		var today = new Date();
		today.setHours(0, 0, 0, 0);

		var html = '';
		for (var i = 0; i < leading; i++) {
			html += '<div class="wccb-calendar__day is-empty"></div>';
		}

		for (var d = 1; d <= daysInMonth; d++) {
			var dateStr = year + '-' + pad(month + 1) + '-' + pad(d);
			var dayDate = new Date(year, month, d);
			var classes = ['wccb-calendar__day'];

			if (dayDate < today) {
				classes.push('is-past', 'is-disabled');
			} else if (slotsByDate[dateStr] && slotsByDate[dateStr].length) {
				classes.push('is-available');
			} else {
				classes.push('is-disabled');
			}

			if (dayDate.getTime() === today.getTime()) {
				classes.push('is-today');
			}
			if (this.selectedDate === dateStr) {
				classes.push('is-selected');
			}

			html += '<button type="button" class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + d + '</button>';
		}

		this.$grid.html(html);
	};

	BookingWidget.prototype.selectDate = function (dateStr) {
		this.selectedDate = dateStr;
		this.$root.find('.wccb-calendar__day').removeClass('is-selected');
		this.$root.find('.wccb-calendar__day[data-date="' + dateStr + '"]').addClass('is-selected');
		this.$selectedDateLabel.text(this.prettyDate(dateStr));

		var slots = this.slotsByDate[dateStr] || [];
		if (!slots.length) {
			this.$slotsList.html('<p>' + wccbData.i18n.noSlots + '</p>');
			return;
		}

		var html = '';
		slots.forEach(function (s) {
			html += '<button type="button" class="wccb-slot" data-slot=\'' + JSON.stringify(s).replace(/'/g, '&apos;') + '\'>'
				+ s.label + '</button>';
		});
		this.$slotsList.html(html);
	};

	BookingWidget.prototype.prettyDate = function (dateStr) {
		var parts = dateStr.split('-');
		var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		return d.toDateString();
	};

	BookingWidget.prototype.selectSlot = function (slot, $slotEl) {
		var self = this;

		this.$root.find('.wccb-slot').removeClass('is-selected');
		$slotEl.addClass('is-selected');
		this.setStatus(wccbData.i18n.holding);

		$.post(wccbData.ajaxUrl, {
			action: 'wccb_hold_slot',
			nonce: wccbData.nonce,
			product_id: this.productId,
			start_utc: slot.start_utc
		}).done(function (resp) {
			if (resp && resp.success) {
				self.selectedSlot = slot;
				self.holdExpiresAt = Date.now() + (resp.data.expires_in * 1000);

				self.$root.find('input[name="wccb_slot_start_utc"]').val(slot.start_utc);
				self.$root.find('input[name="wccb_slot_end_utc"]').val(slot.end_utc);
				self.$root.find('input[name="wccb_slot_local"]').val(self.selectedDate + ' ' + slot.start_local);

				var mins = Math.round(resp.data.expires_in / 60);
				self.setStatus(wccbData.i18n.heldFor.replace('%s', mins));
				self.enableSubmit();
			} else {
				var msg = (resp && resp.data && resp.data.message) || 'Error';
				self.setStatus(msg, 'error');
				$slotEl.removeClass('is-selected');
			}
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
			self.setStatus(msg, 'error');
			$slotEl.removeClass('is-selected');
		});
	};

	BookingWidget.prototype.setStatus = function (text, level) {
		this.$status.text(text || '');
		this.$status.toggleClass('is-error', level === 'error');
	};

	$(function () {
		$('.wccb-booking').each(function () {
			new BookingWidget($(this));
		});
	});
})(jQuery);

;(function ($){
    ;function(Drupal) {
        .ready(function() {

            // EVENTS LISTING
            var $eventsListingGrid = $('#events-listing').isotope({
                itemSelector: '.events-listing__item',
                layoutMode: 'fitRows'
            });

            // store filter for each group
            var eventsFilters = {};
            var audienceUrl = location.href;
            var urlParams = new URLSearchParams(audienceUrl);

            /* If Event List view does not return any results at all, this events-listing div does not exist
               Thus call to show the events no results message since there is no results returned from View
            */
            if ($('#events-listing').length == 0) {
                showHideEventsNoResultsMessage();
            } else {
                // if have results, shift no event message div higher to reduce white space
                $('#events-listing-no-events').css('margin-top', '-70px');
            }

            $(".event-filter-dropdown-holder .btn--event-filter").first().addClass('active');

            $('.event-filter-dropdown-holder .btn--event-filter').on('click', function(e) {
                e.preventDefault();
                var $this = $(this);

                $this.parent().children('.btn--event-filter').removeClass('active');
                $this.addClass('active');

                // Get group key.
                var $buttonGroup = $this.parents('.button-group');
                var filterGroup = $buttonGroup.attr('data-filter-group');

                // Set filter for group.
                eventsFilters[filterGroup] = $this.attr('data-filter');

                // Combine filters.
                var filterValue = concatValues(eventsFilters);
                $eventsListingGrid.isotope({ filter: filterValue });

                // Call function to show or hide events no results message.
                showHideEventsNoResultsMessage();
            });

            // Function to show or hide the events no results message
            function showHideEventsNoResultsMessage() {
                var $eventsListing = $('#events-listing');
                var $noResultsContainer = $('.events-listing__no-events');
                setTimeout(function(){
                    if ($eventsListing.children(':visible').length) {
                        $noResultsContainer.fadeOut();
                    } else {
                        $noResultsContainer.fadeIn();
                    }
                }, 500);
            }

            // flatten object by concatting values
            function concatValues(obj) {
                var value = '';
                for (var prop in obj) {
                    value += obj[prop];
                }
                return value;
            }
        })
    }
    Drupal.behaviors.calendarDatePicker = {
        attach: function(context, settings) {
            // Event Listing Datepicker
            /**
             * Borrowed from https://stackoverflow.com/questions/901115
             * Edited to return an empty string if param is missing.
             */
            function getQueryParameterByName(name, url) {
                if (!url) url = window.location.href;
                name = name.replace(/[\[\]]/g, "\\$&");
                var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
                    results = regex.exec(url);
                if (!results) return '';
                if (!results[2]) return '';
                return decodeURIComponent(results[2].replace(/\+/g, " "));
            }

            // Finds distance of scroll and stores that in a session.
            $(window).scroll(function() {
                sessionStorage.scrollTop = $(this).scrollTop();
            });

            // When the document reloads it applys the stored session scroll distance.
            $(document).ready(function() {
                var href = window.location.href;
                var href = $(location).attr('pathname');
                href.indexOf(1);
                href.toLowerCase();
                href = href.split("/")[1];

                if (sessionStorage.scrollTop != "undefined") {
                    if (href == 'calendar') {
                        $(window).scrollTop(sessionStorage.scrollTop);
                    }
                }
                // sessionStorage.scrollTop.clear();
            });

            /**
             * Assembles the query string corresponding with the new filter values.
             * @param startDate optional start date query parameter (yyyy-mm-dd)
             * @param endDate optional start date query parameter (yyyy-mm-dd)
             */
            function submitDatePickers(startDate, endDate) {
                var tempScrollTop = $(window).scrollTop();
                var buildQuery = [];
                if ( typeof(startDate) !== 'undefined' && startDate != "" ) {
                    buildQuery.push("start_date=" + startDate);
                } else {
                    buildQuery.push("start_date=" + getQueryParameterByName("start_date"));
                }
                if ( typeof(endDate) !== 'undefined' && endDate != "" ) {
                    buildQuery.push("end_date=" + endDate);
                } else {
                    buildQuery.push("end_date=" + getQueryParameterByName("end_date"));
                }
                window.location.reload();
                window.location.search = buildQuery.join('&');
            }

            if ($("#datepicker-start").length) {
                let startDate = getQueryParameterByName('start_date', window.location.href);
                $("#datepicker-start").datepicker({
                    changeMonth: true,
                    dateFormat: 'yy-mm-dd',
                    defaultDate: startDate,
                    inline: true,
                    onSelect: function (dateText, inst) {
                        submitDatePickers(dateText, '');
                        $(this).hide();
                    }
                });
                $("#datepicker-start").hide();

                $("#datepickerImageStart").click(function () {
                    $("#datepicker-start").show();
                    $("#datepicker-end").hide();
                    $("#datepickerImageStart h2").css("color", "#a31e39");
                    $("#datepickerImageEnd h2").css("color", "#000");
                });
                $("#datepicker-start .ui-datepicker-calendar").click(function () {
                    $("#datepicker-start").hide();
                });

                let endDate = getQueryParameterByName('end_date', window.location.href) || startDate;
                $("#datepicker-end").datepicker({
                    changeMonth: true,
                    dateFormat: 'yy-mm-dd',
                    defaultDate: endDate,
                    inline: true,
                    onSelect: function (dateText, inst) {
                        submitDatePickers('', dateText);
                        $(this).hide();
                    }
                });
                $("#datepicker-end").hide();
            }
            $("#datepickerImageEnd").click(function () {
                $("#datepicker-end").show();
                $("#datepicker-start").hide();
                $("#datepickerImageEnd h2").css("color", "#a31e39");
                $("#datepickerImageStart h2").css("color", "#000");
            });
            $("#datepicker-end .ui-datepicker-calendar").click(function () {
                $("#datepicker-end").hide();
            });

            $("#datepicker-end").click(function(){
                $(this).hide();
            });

            $("#byDate").click(function(){
                $("#month-nav").css("border-bottom", "none");
                $("#month-nav h2").css("color", "#bebebe");
                $(this).css("border-bottom", "10px solid #a31e39");
            });

            $("#month-nav").click(function(){
                $("#datepicker-start").hide();
                $("#datepicker-end").hide();
                $("#byDate").css("border-bottom", "none");
                $("#byDate h2").css("color", "#bebebe");
                $(this).css("border-bottom", "10px solid #a31e39");
            });
        }
    };
})
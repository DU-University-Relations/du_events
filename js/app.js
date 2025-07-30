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
})
/**
 * Alchemer Reviews Importer JavaScript
 * 
 * Handles the Import Reviews functionality
 */
(function($, config) {
    'use strict';

    if (!$ || !config || !config.ajaxUrl) {
        return;
    }

    const ajaxErrorText = config.errorText || 'Error: ';
    let importInProgress = false;

    // Toast notification system
    const Toast = {
        container: null,
        
        init() {
            // Create toast container if it doesn't exist
            if (!this.container) {
                this.container = $('<div class="toast-container"></div>');
                $('body').append(this.container);
            }
        },
        
        show(title, message, type = 'success') {
            this.init();
            
            // Map type to appropriate icon and color
            const icons = {
                success: {
                    icon: '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                    color: 'success'
                },
                reject: {
                    icon: '<svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                    color: 'warning'
                },
                error: {
                    icon: '<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
                    color: 'error'
                }
            };
            
            const toastType = icons[type] || icons.success;
            
            const toast = $(`
                <div class="toast ${toastType.color}">
                    <div class="toast-icon">
                        ${toastType.icon}
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                </div>
            `);
            
            this.container.append(toast);
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                toast.addClass('removing');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    };

    $(document).ready(function() {
        const importButton = document.getElementById('import-alchemer-multi-resort-reviews');
        if (importButton) {
            importButton.removeEventListener('click', handleImportButtonClick);
            importButton.addEventListener('click', handleImportButtonClick);
        }

        // Keep delegated binding for late-rendered buttons.
        $(document)
            .off('click.alchemerReviewsImport', '#import-alchemer-multi-resort-reviews')
            .on('click.alchemerReviewsImport', '#import-alchemer-multi-resort-reviews', handleImportButtonClick);

        function handleImportButtonClick(event) {
            const rawEvent = event && (event.originalEvent || event);
            if (rawEvent && rawEvent.alchemerImportHandled) {
                return;
            }
            if (rawEvent) {
                rawEvent.alchemerImportHandled = true;
            }

            event.preventDefault();
            if (importInProgress) {
                return;
            }

            importInProgress = true;

            const $button = $(this);
            const $spinner = $('#import-spinner');
            const $result = $('#import-result');
            
            // Get import parameters
            const maxReviews = $('#max-reviews').val();
            const targetRating = $('#target-rating').val();
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.removeClass('hidden');
            $result.addClass('hidden');
            
            // Add progress bar
            $result.html(`
                <div class="progress-container">
                    <div class="progress-bar" style="width: 0%"></div>
                    <div class="progress-text">
                        <span class="status-text">Starting import...</span>
                    </div>
                </div>
            `).removeClass('hidden');
            
            // Simulate progress for better UX
            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.random() * 10;
                    $('.progress-bar').css('width', Math.min(progress, 90) + '%');
                }
            }, 500);
            
            // Make AJAX request
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'import_alchemer_reviews',
                    nonce: config.nonce,
                    max_reviews: maxReviews,
                    target_rating: targetRating
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            clearInterval(progressInterval);
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            $('.progress-bar').css('width', percentComplete + '%');
                            $('.status-text').text('Importing reviews... ' + Math.round(percentComplete) + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    if (response.success) {
                        // Update progress to 100%
                        $('.progress-bar').css('width', '100%');
                        $('.status-text').text('Import complete! Loading reviews...');
                        
                        // Add debug logging
                        console.log('Import response:', response);
                        
                        // Validate reviews data
                        if (!response.data || !response.data.reviews || !Array.isArray(response.data.reviews)) {
                            console.error('Invalid reviews data:', response.data);
                            $result.html('<div class="alert alert-error">Error: Invalid review data received from server.</div>');
                            return;
                        }
                        
                        // Show reviews for approval after a short delay
                        setTimeout(function() {
                            try {
                            showReviewsForApproval(response.data.reviews, response.data);
                            } catch (error) {
                                console.error('Error showing reviews:', error);
                                $result.html('<div class="alert alert-error">Error: Failed to display reviews. Please check the console for details.</div>');
                            }
                        }, 500);
                    } else {
                        // Show error
                        $result.html('<div class="alert alert-error">' + response.data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    console.error('Import error:', error);
                    $result.html('<div class="alert alert-error">' + ajaxErrorText + 'Failed to import reviews. Please check the console for details.</div>');
                },
                complete: function() {
                    clearInterval(progressInterval);
                    importInProgress = false;
                    $button.prop('disabled', false);
                    $spinner.addClass('hidden');
                }
            });
        }
        
        // Show reviews for approval
        function showReviewsForApproval(reviews, importData = {}) {
            const $result = $('#import-result');

            if (!reviews.length) {
                const message = $('<div>').text(importData.message || 'No matching Alchemer responses were available to import.').html();
                $result.html(
                    '<div class="alert alert-success">' +
                    '<h4 class="text-lg font-medium mb-2">No reviews ready to import</h4>' +
                    '<p>' + message + '</p>' +
                    '</div>'
                );
                return;
            }

            let html = '<div class="reviews-to-approve">';
            
            // Add header with a dedicated class for easy updates
            html += '<h3 class="reviews-counter text-lg font-medium text-gray-700 mb-4">' + 
                    reviews.length + ' Reviews to Process</h3>';
            
            // Add each review
            reviews.forEach(function(review) {
                // Validate review data structure
                if (!review || !review.review_data) {
                    console.error('Invalid review data:', review);
                    return;
                }

                const reviewData = review.review_data;
                const responseId = reviewData.response_id;
                
                html += '<div class="review-card mb-6 p-4 bg-white rounded-lg shadow" data-response-id="' + responseId + '">';
                
                // Review header
                html += '<div class="flex justify-between items-center mb-4">';
                html += '<div class="flex items-center">';
                html += '<span class="text-lg font-medium">' + (reviewData.reviewer_name || 'Anonymous') + '</span>';
                html += '<span class="ml-2 text-sm text-gray-500">' + (reviewData.rating || '0') + ' ★</span>';
                html += '</div>';

                html += '</div>';
                
                // Original content
                html += '<div class="mb-4">';
                html += '<h4 class="text-sm font-medium text-gray-700 mb-2">Original Review</h4>';
                html += '<div class="p-3 bg-gray-50 rounded">' + (reviewData.content || 'No content available') + '</div>';
                html += '</div>';
                

                
                // Action buttons
                html += '<div class="review-actions">';
                html += '<button class="edit-review alchemer-button alchemer-button-secondary" data-response-id="' + responseId + '">Edit</button>';
                html += '<button class="reject-review alchemer-button alchemer-button-secondary" data-response-id="' + responseId + '">Reject</button>';
                html += '<button class="accept-review alchemer-button alchemer-button-primary" data-response-id="' + responseId + '">Accept</button>';

                html += '</div>';
                
                html += '</div>'; // End review-card
            });
            
            html += '</div>'; // End reviews-to-approve
            
            $result.html(html);
            
            // Create edit modal if it doesn't exist
            if ($('#edit-review-modal').length === 0) {
                const modalHtml = `
                    <div id="edit-review-modal" class="alchemer-modal">
                        <div class="alchemer-modal-content">
                            <div class="modal-header">
                                <h3 class="text-xl font-medium mb-4">Edit Review</h3>
                            </div>
                            <div class="modal-body">
                                <textarea id="edit-review-content" class="w-full h-40 p-3 border rounded mb-4"></textarea>
                            </div>
                            <div class="modal-footer flex justify-end gap-2">
                                <button id="cancel-edit" class="alchemer-button alchemer-button-secondary">Cancel</button>
                                <button id="save-edit" class="alchemer-button alchemer-button-primary">Save & Accept</button>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);

                // Add modal event handlers
                $('#cancel-edit').on('click', function() {
                    hideModal();
                });

                // Close modal when clicking outside
                $('#edit-review-modal').on('click', function(e) {
                    if (e.target === this) {
                        hideModal();
                    }
                });
            }
            
            // Add event handlers for accept/reject buttons
            $('.accept-review').on('click', function() {
                const responseId = $(this).data('response-id');
                const review = reviews.find(r => r.review_data.response_id == responseId);
                if (review) {
                    processReview(responseId, review, true, false);
                } else {
                    console.error('Review not found for response ID:', responseId);
                    Toast.show('Error', 'Review not found', 'error');
                }
            });
            

            
            $('.reject-review').on('click', function() {
                const responseId = $(this).data('response-id');
                const review = reviews.find(r => r.review_data.response_id == responseId);
                if (review) {
                    processReview(responseId, review, false, false);
                } else {
                    console.error('Review not found for response ID:', responseId);
                    Toast.show('Error', 'Review not found', 'error');
                }
            });

            $('.edit-review').on('click', function() {
                const responseId = $(this).data('response-id');
                const review = reviews.find(r => r.review_data.response_id == responseId);
                if (review) {
                    // Show modal with current content
                    showModal(review.review_data.content);
                    
                    // Update save button handler
                    $('#save-edit').off('click').on('click', function() {
                        const editedContent = $('#edit-review-content').val();
                        if (editedContent.trim()) {
                            // Update review content
                            review.review_data.content = editedContent;
                            // Process with edited content
                            processReview(responseId, review, true, true);
                            // Hide modal
                            hideModal();
                        } else {
                            Toast.show('Error', 'Review content cannot be empty', 'error');
                        }
                    });
                } else {
                    console.error('Review not found for response ID:', responseId);
                    Toast.show('Error', 'Review not found', 'error');
                }
            });

            // Helper functions for modal
            function showModal(content) {
                $('#edit-review-content').val(content);
                $('#edit-review-modal').addClass('show');
                // Prevent scrolling on the body
                $('body').css('overflow', 'hidden');
            }

            function hideModal() {
                $('#edit-review-modal').removeClass('show');
                // Restore scrolling on the body
                $('body').css('overflow', '');
            }
        }
        
        // Process a single review
        function processReview(responseId, review, accept, edited = false) {
            const $reviewCard = $('.review-card[data-response-id="' + responseId + '"]');
            const $button = accept ? $reviewCard.find('.accept-review') : $reviewCard.find('.reject-review');
            
            $button.prop('disabled', true);
            $reviewCard.addClass('processing');
            
            // Prepare review data for processing
            const reviewData = {
                response_id: review.review_data.response_id,
                reviewer_name: review.review_data.reviewer_name || 'Anonymous',
                rating: review.review_data.rating || 0,
                content: review.review_data.content,
                post_date: review.review_data.post_date || new Date().toISOString(),
                review_date: review.review_data.review_date || new Date().toLocaleDateString()
            };
            
            // Debug log
            console.log('Sending review data:', reviewData);
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'process_alchemer_review',
                    nonce: config.nonce,
                    review_data: reviewData,
                    accept: accept ? 1 : 0,
                    edited: edited ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        // Show success toast with appropriate message and type
                        Toast.show(
                            accept ? 'Review Accepted' : 'Review Rejected',
                            accept ? 
                                'The review has been published.' :
                                'The review has been saved as a draft. You can find it in the Reviews list.',
                            accept ? 'success' : 'reject'
                        );
                        
                        // Remove the review card with a fade animation
                        $reviewCard.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Update the remaining reviews count using the dedicated class
                            const remainingReviews = $('.review-card').length;
                            if (remainingReviews > 0) {
                                $('.reviews-counter').text(remainingReviews + ' Reviews to Process');
                            } else {
                                $('#import-result').html(
                                    '<div class="alert alert-success">' +
                                    '<h4 class="text-lg font-medium mb-2">All reviews have been processed!</h4>' +
                                    '<p>You can find the published reviews in the Reviews list and drafts in the Drafts section.</p>' +
                                    '</div>'
                                );
                            }
                        });
                    } else {
                        // Show error toast
                        Toast.show(
                            'Error Processing Review',
                            response.data.message || 'An error occurred while processing the review.',
                            'error'
                        );
                        $button.prop('disabled', false);
                        $reviewCard.removeClass('processing');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Process error:', error);
                    console.error('Response:', xhr.responseText);
                    
                    // Show error toast
                    Toast.show(
                        'Error Processing Review',
                        'Failed to process review. Please check the console for details.',
                        'error'
                    );
                    
                    $button.prop('disabled', false);
                    $reviewCard.removeClass('processing');
                }
            });
        }
    });

})(
    typeof jQuery !== 'undefined' ? jQuery : window.jQuery,
    typeof alchemerReviewsImporter !== 'undefined' ? alchemerReviewsImporter : (window.alchemerReviewsImporter || {})
);

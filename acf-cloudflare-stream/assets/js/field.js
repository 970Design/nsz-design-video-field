/**
 * Included when cloudflare_stream fields are rendered for editing by publishers.
 */
(function ($) {
	function sanitizeHTML(str) {
		return str.replace(/[&<>"']/g, match => ({
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#39;'
		})[match]);
	}

	function validateFile(file, error_area) {
		const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
		if (!allowedTypes.includes(file.type)) {
			error_area.html('Please upload a valid video file (MP4, WebM, or OGG).').show();
			return false;
		}

		const maxSize = 1024 * 1024 * 500; // 500MB
		if (file.size > maxSize) {
			error_area.html('File size must be less than 500MB').show();
			return false;
		}
		return true;
	}

	function validateResponse(response) {
		if (!response || !response.result) {
			throw new Error('Invalid response format');
		}
		return response;
	}

	function initialize_field($field) {

		const $fileInput = $field.find('.nsz-cloudflare-stream-file');
		const $closeModal = $field.find('.nsz-cloudflare-stream-close-modal');
		const $browseModal = $field.find('.nsz-cloudflare-stream-browse-modal');
		const $clearVideo = $field.find('.nsz-cloudflare-stream-clear-video');

		$fileInput.off('change');

		// Function to delete video from Cloudflare Stream
		function deleteCloudflareVideo(videoId, listItem, cfs_wrap) {
			const videoName = sanitizeHTML(listItem.find('strong').text());
			if (!videoId || !window.confirm(`Are you sure you want to delete "${videoName}"?`)) {
				return;
			}

			$.ajax({
				type: "DELETE",
				url: `https://api.cloudflare.com/client/v4/accounts/${nsz_cloudflare_stream.account_id}/stream/${videoId}`,
				headers: {
					"Authorization": `Bearer ${nsz_cloudflare_stream.api_token}`,
					"X-Auth-Email": nsz_cloudflare_stream.account_email,
					"X-Auth-Key": nsz_cloudflare_stream.api_token
				},
				timeout: 30000,
				success: function(response) {
					try {
						const validatedResponse = validateResponse(response);
						if (validatedResponse.success) {
							listItem.remove();
							cfs_wrap.find('.cloudflare-stream-success')
								.html('Video deleted successfully')
								.show()
								.delay(3000)
								.fadeOut();
						}
					} catch(e) {
						cfs_wrap.find('.cloudflare-stream-error')
							.html('Invalid server response')
							.show();
					}
				},
				error: function(error) {
					console.error(error);
					cfs_wrap.find('.cloudflare-stream-error')
						.html('Error deleting video')
						.show();
				}
			});
		}


		/**
		 * $field is a jQuery object wrapping field elements in the editor.
		 */

		$closeModal.on('click', function(e) {
			e.preventDefault();
			$(this).closest('.cloudflare-stream-wrapper')
				.find('.nsz-cloudflare-stream-modal')
				.attr('open', false);
		});

		$browseModal.on('click', function (e) {
			e.preventDefault();

			const cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');
			const error_area = cfs_wrap.find('.cloudflare-stream-error');

			cfs_wrap.find('.nsz-cloudflare-stream-search').remove();

			const searchBox = $('<input type="text" class="nsz-cloudflare-stream-search" placeholder="Search videos..." style="width: 100%; margin-bottom: 1rem;">');
			const modalListing = cfs_wrap.find('.nsz-cloudflare-stream-modal-listing');
			modalListing.before(searchBox);

			$.ajax({
				type: "GET",
				url: 'https://api.cloudflare.com/client/v4/accounts/' + nsz_cloudflare_stream.account_id + '/stream/',
				headers: {
					"Authorization": "Bearer " + nsz_cloudflare_stream.api_token,
					"X-Auth-Email": nsz_cloudflare_stream.account_email,
					"X-Auth-Key": nsz_cloudflare_stream.api_token
				},
				timeout: 30000,
				success: function (data) {

					try {
						const validatedData = validateResponse(data);

						if (data.result.length) {
							let video_list = cfs_wrap.find('.nsz-cloudflare-stream-modal-listing');
							video_list.html('');

							// Add the search handler
							searchBox.on('input', function() {
								const searchTerm = $(this).val().toLowerCase();
								const filteredResults = data.result.filter(video =>
									video.meta.name.toLowerCase().includes(searchTerm)
								);

								// Recalculate pagination with filtered results
								currentPage = 1;
								const totalFilteredPages = Math.ceil(filteredResults.length / itemsPerPage);

								// Update pagination controls
								paginationContainer.html('');
								for (let i = 1; i <= totalFilteredPages; i++) {
									const pageButton = $(`<button class="button-${i === currentPage ? 'primary' : 'secondary'} page-button ${i === currentPage ? 'active' : ''}">${i}</button>`);
									pageButton.on('click', function(e) {
										e.preventDefault();
										currentPage = i;
										displayVideosForPage(currentPage, filteredResults);
										paginationContainer.find('.page-button').removeClass('active button-primary').addClass('button-secondary');
										$(this).removeClass('button-secondary').addClass('active button-primary');
									});
									paginationContainer.append(pageButton);
								}

								// Display filtered results
								displayVideosForPage(1, filteredResults);
							});

							// Remove any existing pagination controls first
							cfs_wrap.find('.nsz-cloudflare-stream-pagination-controls').remove();

							// Create pagination controls
							let paginationContainer = $('<div class="nsz-cloudflare-stream-pagination-controls"></div>');
							let currentPage = 1;
							const itemsPerPage = 50;
							const totalPages = Math.ceil(data.result.length / itemsPerPage);

							// Function to display videos for current page
							function displayVideosForPage(pageNum, results = data.result) {
								video_list.html('');
								const startIndex = (pageNum - 1) * itemsPerPage;
								const endIndex = Math.min(startIndex + itemsPerPage, results.length);

								for (let i = startIndex; i < endIndex; i++) {
									const video = results[i];
									let uploadDate = new Date(video.uploaded);
									let formattedDate = uploadDate.toLocaleDateString('en-US', {
										month: 'numeric',
										day: 'numeric',
										year: 'numeric'
									}) + ' ' + uploadDate.toLocaleTimeString('en-US', {
										hour: 'numeric',
										minute: '2-digit',
										hour12: true
									}).toLowerCase();
									let list_item = $('<li class="nsz-cloudflare-stream-modal-item" data-video-id="' + video.uid + '"><img src="' + video.thumbnail + '" alt="' + video.meta.name + '"><div><strong>File:</strong> ' + video.meta.name + ' <br /><strong>Duration:</strong> ' + video.duration + 'sec <br /><strong>Uploaded:</strong> ' + formattedDate + '<br /><button style="margin-right: 1.25rem; margin-top: .35rem;" class="button-primary nsz-select-video">Select</button><button style="margin-top: .35rem;" class="button-secondary nsz-delete-video">Delete</button></div></li>');

									// Add the delete button click handler
									list_item.find('.nsz-delete-video').on('click', function (e) {
										e.preventDefault();
										deleteCloudflareVideo(video.uid, list_item, cfs_wrap);
									});

									list_item.find('.nsz-select-video').on('click', function (e) {
										e.preventDefault();
										cfs_wrap.find('.data-hls').val(video.playback.hls);
										cfs_wrap.find('.data-dash').val(video.playback.dash);
										cfs_wrap.find('.data-thumbnail').val(video.thumbnail);
										cfs_wrap.find('.data-preview').val(video.preview);
										cfs_wrap.find('.data-filename').val(video.meta.name);
										cfs_wrap.find('.data-filename-display').html(video.meta.name);
										cfs_wrap.find('.cloudflare-video-details').show();
										cfs_wrap.find('.cloudflare-video-thumbnail-preview').attr('src', video.thumbnail);
										cfs_wrap.find('.wrap-upload-field').hide();
										cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', false);
									});

									video_list.append(list_item);
								}
							}

							// Create pagination buttons
							for (let i = 1; i <= totalPages; i++) {
								const pageButton = $(`<button class="button-${i === currentPage ? 'primary' : 'secondary'} page-button ${i === currentPage ? 'active' : ''}">${i}</button>`);
								pageButton.on('click', function (e) {
									e.preventDefault();
									currentPage = i;
									displayVideosForPage(currentPage);
									// Update button classes when switching pages
									paginationContainer.find('.page-button').removeClass('active button-primary').addClass('button-secondary');
									$(this).removeClass('button-secondary').addClass('active button-primary');
								});
								paginationContainer.append(pageButton);
							}

							// Display first page and add pagination controls
							displayVideosForPage(currentPage);
							video_list.after(paginationContainer);

						}
					} catch(e) {
						error_area.html('Invalid server response').show();
					}

					cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', true);
				},
				error: function (data) {
					console.log(data);
					error_area.html('Error fetching videos from Cloudflare.').show();
				}
			});
		});


		$clearVideo.on('click', function (e) {
			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');
			cfs_wrap.find('.data-hls').val('');
			cfs_wrap.find('.data-dash').val('');
			cfs_wrap.find('.data-thumbnail').val('');
			cfs_wrap.find('.data-preview').val('');
			cfs_wrap.find('.data-filename').val('');
			cfs_wrap.find('.data-filename-display').html('');
			cfs_wrap.find('.cloudflare-video-details').hide();
			cfs_wrap.find('.cloudflare-video-thumbnail-preview').attr('src', '');
			cfs_wrap.find('.wrap-upload-field').show();
		});

		$fileInput.on('change', function (e) {

			const cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');
			const error_area = cfs_wrap.find('.cloudflare-stream-error');
			const file = e.target.files[0];

			if (!file || !validateFile(file, error_area)) {
				return;
			}

			let currentUpload = $field.data('currentUpload');
			if (currentUpload) {
				currentUpload.abort();
			}

			if ($field.data('isUploading')) {
				error_area.html('Upload already in progress').show();
				return;
			}

			let video_id = null;

			let hls_input = cfs_wrap.find('.data-hls');
			let dash_input = cfs_wrap.find('.data-dash');
			let thumbnail_input = cfs_wrap.find('.data-thumbnail');
			let preview_input = cfs_wrap.find('.data-preview');
			let filename_input = cfs_wrap.find('.data-filename');
			let video_details = cfs_wrap.find('.cloudflare-video-details');
			let filename_display = cfs_wrap.find('.data-filename-display');

			let success_area = cfs_wrap.find('.cloudflare-stream-success');
			let progress_wrap = cfs_wrap.find('.cloudflare-stream-progress-wrap');
			let progress_bar = cfs_wrap.find('.cloudflare-stream-progress-bar');
			let video_thumbnail = cfs_wrap.find('.cloudflare-video-thumbnail-preview');
			let video_upload = cfs_wrap.find('.wrap-upload-field');

			error_area.html('').hide();
			success_area.html('').hide();

			if (!file.type.includes('video')) {
				error_area.html('Please upload a video file.').show();
				return;
			}

			$field.data('isUploading', true);

			let maxRetries = 3;
			let currentRetry = 0;

			let upload = new tus.Upload(file, {
				endpoint: `https://api.cloudflare.com/client/v4/accounts/${nsz_cloudflare_stream.account_id}/stream`,
				retryDelays: [0, 3000, 5000, 10000],
				metadata: {
					name: file.name,
					filetype: file.type,
				},
				headers: {
					"Authorization": "Bearer " + nsz_cloudflare_stream.api_token,
					"X-Auth-Email": nsz_cloudflare_stream.account_email,
					"X-Auth-Key": nsz_cloudflare_stream.api_token
				},
				uploadSize: file.size,
				chunkSize: 50 * 1024 * 1024,
				onError: function (error) {
					currentRetry++;

					// Check if max retries reached or if it's a fatal error
					if (currentRetry > maxRetries ||
						error.name === 'AuthError' ||
						error.name === 'NotFoundError') {

						$field.data('isUploading', false);
						$field.data('currentUpload', null);
						progress_wrap.hide();

						// Display specific error messages
						let errorMessage = 'Upload failed: ';
						if (error.name === 'AuthError') {
							errorMessage += 'Authentication failed. Please check your credentials.';
						} else if (error.name === 'NotFoundError') {
							errorMessage += 'Upload endpoint not found.';
						} else if (currentRetry > maxRetries) {
							errorMessage += 'Maximum retry attempts reached. Please try again later.';
						} else {
							errorMessage += error.message || 'Unknown error occurred.';
						}

						error_area.html(errorMessage).show();

						// Abort the upload
						upload.abort();
						return;
					}

					console.error('Upload error:', error);
					error_area.html('Upload attempt ' + currentRetry + ' of ' + maxRetries + ' failed. Retrying...').show();
				},
				onProgress: function (bytesUploaded, bytesTotal) {
					var percentage = ((bytesUploaded / bytesTotal) * 100).toFixed(2);
					progress_wrap.show();
					progress_bar.val(percentage);
				},
				onAfterResponse: function (req, res) {
					//get video id from header
					video_id = res.getHeader("stream-media-id");
				},
				onSuccess: function () {

					$field.data('isUploading', false);
					$field.data('currentUpload', null);

					progress_wrap.hide();

					if (video_id) {
						success_area.html('Video Uploaded!  Fetching media URLs..').show();

						$.ajax({
							type: "GET",
							url: 'https://api.cloudflare.com/client/v4/accounts/' + nsz_cloudflare_stream.account_id + '/stream/' + video_id,
							headers: {
								"Authorization": "Bearer " + nsz_cloudflare_stream.api_token,
								"X-Auth-Email": nsz_cloudflare_stream.account_email,
								"X-Auth-Key": nsz_cloudflare_stream.api_token
							},
							success: function (data) {
								hls_input.val(data.result.playback.hls);
								dash_input.val(data.result.playback.dash);
								thumbnail_input.val(data.result.thumbnail);
								preview_input.val(data.result.preview);
								filename_input.val(data.result.meta.name);
								filename_display.html(data.result.meta.name);
								video_details.show();
								video_thumbnail.attr('src', data.result.thumbnail)

								success_area.html('Upload complete!').show();
								video_upload.hide();
							},
							error: function (data) {
								console.log(data);
								error_area.html('Error fetching video URLs from Cloudflare.').show();
							}
						});
					} else {
						error_area.html('Video ID not found.').show();
					}
				},
				onError: function(error) {
					$field.data('isUploading', false);
					$field.data('currentUpload', null);
					error_area.html('Failed connecting to Cloudflare stream: ' + error).show();
				}
			});

			$field.data('currentUpload', upload);

			// Check if there are any previous uploads to continue.
			upload.findPreviousUploads().then(function (previousUploads) {
				if (previousUploads.length) {
					upload.resumeFromPreviousUpload(previousUploads[0]);
				}

				// Start the upload
				upload.start();
			});
		});

	}

	if (typeof acf.add_action !== 'undefined') {
		/**
		 * Run initialize_field when existing fields of this type load,
		 * or when new fields are appended via repeaters or similar.
		 */
		acf.add_action('ready_field/type=cloudflare_stream', initialize_field);
		acf.add_action('append_field/type=cloudflare_stream', initialize_field);
	}
})(jQuery);

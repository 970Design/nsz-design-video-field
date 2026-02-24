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
		const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/mpeg', 'video/x-msvideo'];
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

	/**
	 * Extract a human-readable error from a Cloudflare API response object.
	 */
	function cfErrorMessage(data) {
		if (data && data.errors && data.errors.length) {
			return data.errors[0].message || JSON.stringify(data.errors[0]);
		}
		return 'Unknown error';
	}

	/**
	 * Handle autoplay/muted relationship
	 * When autoplay is checked, muted must be checked and disabled
	 */
	function handleAutoplayMutedRelationship($field) {
		const $autoplayCheckbox = $field.find('.data-autoplay');
		const $mutedCheckbox = $field.find('.data-muted');
		const $mutedHidden = $field.find('input[type="hidden"][name*="[muted]"]');

		function updateMutedState() {
			if ($autoplayCheckbox.is(':checked')) {
				$mutedCheckbox.prop('checked', true);
				$mutedCheckbox.prop('disabled', true);
				$mutedHidden.val('1');
			} else {
				$mutedCheckbox.prop('disabled', false);
			}
		}

		updateMutedState();

		$autoplayCheckbox.off('change.autoplay').on('change.autoplay', function() {
			updateMutedState();
		});

		$mutedCheckbox.off('change.muted').on('change.muted', function() {
			if ($autoplayCheckbox.is(':checked') && !$(this).is(':checked')) {
				$(this).prop('checked', true);
				$mutedHidden.val('1');
			} else {
				$mutedHidden.val($(this).is(':checked') ? '1' : '0');
			}
		});
	}

	/**
	 * Handle play_scrolled_into_view relationship with muted and loop
	 * When play_scrolled_into_view is checked, muted and loop must be checked and disabled
	 */
	function handlePlayScrolledIntoViewRelationship($field) {
		const $playScrolledCheckbox = $field.find('.data-play-scrolled-into-view');
		const $mutedCheckbox = $field.find('.data-muted');
		const $loopCheckbox = $field.find('.data-loop');
		const $mutedHidden = $field.find('input[type="hidden"][name*="[muted]"]');
		const $loopHidden = $field.find('input[type="hidden"][name*="[loop]"]');

		function updateDependentStates() {
			if ($playScrolledCheckbox.is(':checked')) {
				$mutedCheckbox.prop('checked', true);
				$loopCheckbox.prop('checked', true);
				$mutedCheckbox.prop('disabled', true);
				$loopCheckbox.prop('disabled', true);
				$mutedHidden.val('1');
				$loopHidden.val('1');
			} else {
				$mutedCheckbox.prop('disabled', false);
				$loopCheckbox.prop('disabled', false);
			}
		}

		updateDependentStates();

		$playScrolledCheckbox.off('change.playScrolled').on('change.playScrolled', function() {
			updateDependentStates();
		});

		$mutedCheckbox.off('change.playScrolledMuted').on('change.playScrolledMuted', function() {
			if ($playScrolledCheckbox.is(':checked') && !$(this).is(':checked')) {
				$(this).prop('checked', true);
				$mutedHidden.val('1');
			}
		});

		$loopCheckbox.off('change.playScrolledLoop').on('change.playScrolledLoop', function() {
			if ($playScrolledCheckbox.is(':checked') && !$(this).is(':checked')) {
				$(this).prop('checked', true);
				$loopHidden.val('1');
			}
		});
	}

	function initialize_field($field) {

		const $fileInput = $field.find('.nsz-cloudflare-stream-file');
		const $closeModal = $field.find('.nsz-cloudflare-stream-close-modal');
		const $browseModal = $field.find('.nsz-cloudflare-stream-browse-modal');
		const $clearVideo = $field.find('.nsz-cloudflare-stream-clear-video');

		$fileInput.off('change');

		handleAutoplayMutedRelationship($field);
		handlePlayScrolledIntoViewRelationship($field);

		// Delete a video via WP AJAX proxy
		function deleteCloudflareVideo(videoId, listItem, cfs_wrap) {
			const videoName = sanitizeHTML(listItem.find('strong').text());
			if (!videoId || !window.confirm(`Are you sure you want to delete "${videoName}"?`)) {
				return;
			}

			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: {
					action:   'nsz_cfstream_delete_video',
					nonce:    nsz_cloudflare_stream.nonce,
					video_id: videoId
				},
				timeout: 30000,
				success: function(response) {
					if (!response || !response.success) {
						cfs_wrap.find('.cloudflare-stream-error')
							.html('Error deleting video: ' + cfErrorMessage(response))
							.show();
						return;
					}
					listItem.remove();
					cfs_wrap.find('.cloudflare-stream-success')
						.html('Video deleted successfully')
						.show()
						.delay(3000)
						.fadeOut();
				},
				error: function(error) {
					console.error(error);
					cfs_wrap.find('.cloudflare-stream-error')
						.html('Error deleting video')
						.show();
				}
			});
		}

		$closeModal.on('click', function(e) {
			e.preventDefault();
			$(this).closest('.cloudflare-stream-wrapper')
				.find('.nsz-cloudflare-stream-modal')
				.attr('open', false);
		});

		// Browse existing videos via WP AJAX proxy
		$browseModal.on('click', function (e) {
			e.preventDefault();

			const cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');
			const error_area = cfs_wrap.find('.cloudflare-stream-error');

			cfs_wrap.find('.nsz-cloudflare-stream-search').remove();

			const searchBox = $('<input type="text" class="nsz-cloudflare-stream-search" placeholder="Search videos..." style="width: 100%; margin-bottom: 1rem;">');
			const modalListing = cfs_wrap.find('.nsz-cloudflare-stream-modal-listing');
			modalListing.before(searchBox);

			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: {
					action: 'nsz_cfstream_list_videos',
					nonce:  nsz_cloudflare_stream.nonce
				},
				timeout: 30000,
				success: function (data) {
					if (!data || !data.success) {
						error_area.html('Cloudflare API error: ' + cfErrorMessage(data)).show();
						return;
					}

					if (data.result.length) {
						let video_list = cfs_wrap.find('.nsz-cloudflare-stream-modal-listing');
						video_list.html('');

						searchBox.on('input', function() {
							const searchTerm = $(this).val().toLowerCase();
							const filteredResults = data.result.filter(video =>
								video.meta.name.toLowerCase().includes(searchTerm)
							);

							currentPage = 1;
							const totalFilteredPages = Math.ceil(filteredResults.length / itemsPerPage);

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

							displayVideosForPage(1, filteredResults);
						});

						cfs_wrap.find('.nsz-cloudflare-stream-pagination-controls').remove();

						let paginationContainer = $('<div class="nsz-cloudflare-stream-pagination-controls"></div>');
						let currentPage = 1;
						const itemsPerPage = 50;
						const totalPages = Math.ceil(data.result.length / itemsPerPage);

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
								let list_item = $('<li class="nsz-cloudflare-stream-modal-item" data-video-id="' + sanitizeHTML(video.uid) + '"><img src="' + sanitizeHTML(video.thumbnail) + '" alt="' + sanitizeHTML(video.meta.name) + '"><div><strong>File:</strong> ' + sanitizeHTML(video.meta.name) + ' <br /><strong>Duration:</strong> ' + sanitizeHTML(String(video.duration)) + 'sec <br /><strong>Uploaded:</strong> ' + sanitizeHTML(formattedDate) + '<br /><button style="margin-right: 1.25rem; margin-top: .35rem;" class="button-primary nsz-select-video">Select</button><button style="margin-top: .35rem;" class="button-secondary nsz-delete-video">Delete</button></div></li>');

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
									cfs_wrap.find('.data-filename-display').text(video.meta.name);
									cfs_wrap.find('.cloudflare-video-details').show();
									cfs_wrap.find('.cloudflare-video-thumbnail-preview').attr('src', video.thumbnail);
									cfs_wrap.find('.wrap-upload-field').hide();
									cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', false);
								});

								video_list.append(list_item);
							}
						}

						for (let i = 1; i <= totalPages; i++) {
							const pageButton = $(`<button class="button-${i === currentPage ? 'primary' : 'secondary'} page-button ${i === currentPage ? 'active' : ''}">${i}</button>`);
							pageButton.on('click', function (e) {
								e.preventDefault();
								currentPage = i;
								displayVideosForPage(currentPage);
								paginationContainer.find('.page-button').removeClass('active button-primary').addClass('button-secondary');
								$(this).removeClass('button-secondary').addClass('active button-primary');
							});
							paginationContainer.append(pageButton);
						}

						displayVideosForPage(currentPage);
						video_list.after(paginationContainer);
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

			if (!file.type.includes('video')) {
				error_area.html('Please upload a video file.').show();
				return;
			}

			let hls_input       = cfs_wrap.find('.data-hls');
			let dash_input      = cfs_wrap.find('.data-dash');
			let thumbnail_input = cfs_wrap.find('.data-thumbnail');
			let preview_input   = cfs_wrap.find('.data-preview');
			let filename_input  = cfs_wrap.find('.data-filename');
			let video_details   = cfs_wrap.find('.cloudflare-video-details');
			let filename_display = cfs_wrap.find('.data-filename-display');
			let success_area    = cfs_wrap.find('.cloudflare-stream-success');
			let progress_wrap   = cfs_wrap.find('.cloudflare-stream-progress-wrap');
			let progress_bar    = cfs_wrap.find('.cloudflare-stream-progress-bar');
			let video_thumbnail = cfs_wrap.find('.cloudflare-video-thumbnail-preview');
			let video_upload    = cfs_wrap.find('.wrap-upload-field');

			error_area.html('').hide();
			success_area.html('').hide();

			$field.data('isUploading', true);

			// Step 1: Ask the server for a pre-authorized direct upload URL.
			// Keeps all credentials server-side; the returned uploadURL can be
			// used from the browser without any auth headers.
			success_area.html('Preparing upload...').show();

			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: {
					action:    'nsz_cfstream_create_upload_url',
					nonce:     nsz_cloudflare_stream.nonce,
					file_name: file.name
				},
				timeout: 30000,
				success: function (cfData) {
					if (!cfData || !cfData.result || !cfData.result.uploadURL) {
						$field.data('isUploading', false);
						success_area.hide();
						error_area.html('Failed to initialize upload: ' + cfErrorMessage(cfData)).show();
						return;
					}

					success_area.hide();

					const video_id  = cfData.result.uid;
					const uploadURL = cfData.result.uploadURL;

					// Step 2: Upload the file directly to the pre-authorized URL.
					// No auth headers needed — the URL is already credentialed.
					const formData = new FormData();
					formData.append('file', file);

					const xhr = new XMLHttpRequest();
					xhr.open('POST', uploadURL);

					xhr.upload.addEventListener('progress', function (e) {
						if (e.lengthComputable) {
							const percentage = ((e.loaded / e.total) * 100).toFixed(2);
							progress_wrap.show();
							progress_bar.val(percentage);
						}
					});

					xhr.addEventListener('load', function () {
						$field.data('isUploading', false);
						$field.data('currentUpload', null);
						progress_wrap.hide();

						if (xhr.status < 200 || xhr.status >= 400) {
							error_area.html('Upload failed (HTTP ' + xhr.status + '): ' + xhr.responseText).show();
							return;
						}

						success_area.html('Video Uploaded! Fetching media URLs...').show();

						// Step 3: Fetch video details via WP AJAX proxy.
						$.ajax({
							type: "POST",
							url: ajaxurl,
							data: {
								action:   'nsz_cfstream_get_video',
								nonce:    nsz_cloudflare_stream.nonce,
								video_id: video_id
							},
							success: function (data) {
								if (!data || !data.result) {
									error_area.html('Upload succeeded but could not fetch video details: ' + cfErrorMessage(data)).show();
									return;
								}
								hls_input.val(data.result.playback.hls);
								dash_input.val(data.result.playback.dash);
								thumbnail_input.val(data.result.thumbnail);
								preview_input.val(data.result.preview);
								filename_input.val(data.result.meta.name);
								filename_display.text(data.result.meta.name);
								video_details.show();
								video_thumbnail.attr('src', data.result.thumbnail);

								success_area.html('Upload complete!').show();
								video_upload.hide();

								handleAutoplayMutedRelationship($field);
							},
							error: function (data) {
								console.log(data);
								error_area.html('Error fetching video URLs from Cloudflare.').show();
							}
						});
					});

					xhr.addEventListener('error', function () {
						$field.data('isUploading', false);
						$field.data('currentUpload', null);
						progress_wrap.hide();
						error_area.html('Upload failed. Please check your connection and try again.').show();
					});

					$field.data('currentUpload', xhr);
					xhr.send(formData);
				},
				error: function () {
					$field.data('isUploading', false);
					error_area.html('Failed to initialize upload. Please try again.').show();
				}
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

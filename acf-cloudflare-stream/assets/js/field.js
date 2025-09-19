/**
 * Included when cloudflare_stream fields are rendered for editing by publishers.
 */
(function ($) {
	function initialize_field($field) {

		// Function to delete video from Cloudflare Stream
		function deleteCloudflareVideo(videoId, listItem, cfs_wrap) {
			if (confirm('Are you sure you want to delete this video?')) {
				$.ajax({
					type: "DELETE",
					url: 'https://api.cloudflare.com/client/v4/accounts/' + nsz_cloudflare_stream.account_id + '/stream/' + videoId,
					headers: {
						"Authorization": "Bearer " + nsz_cloudflare_stream.api_token,
						"X-Auth-Email": nsz_cloudflare_stream.account_email,
						"X-Auth-Key": nsz_cloudflare_stream.api_token
					},
					success: function (response) {
						if (response.success) {
							listItem.remove();
							// Show success message
							cfs_wrap.find('.cloudflare-stream-success').html('Video deleted successfully').show().delay(3000).fadeOut();
						}
					},
					error: function (error) {
						console.error(error);
						cfs_wrap.find('.cloudflare-stream-error').html('Error deleting video').show();
					}
				});
				listItem.remove();
			}
		}

		/**
		 * $field is a jQuery object wrapping field elements in the editor.
		 */

		$('.nsz-cloudflare-stream-close-modal').on('click', function (e) {
			e.preventDefault();
			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');
			cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', false);
		});

		$('.nsz-cloudflare-stream-browse-modal').on('click', function (e) {
			e.preventDefault();

			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');

			$.ajax({
				type: "GET",
				url: 'https://api.cloudflare.com/client/v4/accounts/' + nsz_cloudflare_stream.account_id + '/stream/',
				headers: {
					"Authorization": "Bearer " + nsz_cloudflare_stream.api_token,
					"X-Auth-Email": nsz_cloudflare_stream.account_email,
					"X-Auth-Key": nsz_cloudflare_stream.api_token
				},
				success: function (data) {

					if (data.result.length) {
						let video_list = cfs_wrap.find('.nsz-cloudflare-stream-modal-listing');
						video_list.html('');

						// Remove any existing pagination controls first
						cfs_wrap.find('.nsz-cloudflare-stream-pagination-controls').remove();

						// Create pagination controls
						let paginationContainer = $('<div class="nsz-cloudflare-stream-pagination-controls"></div>');
						let currentPage = 1;
						const itemsPerPage = 50;
						const totalPages = Math.ceil(data.result.length / itemsPerPage);


						// Function to display videos for current page
						function displayVideosForPage(pageNum) {
							video_list.html('');
							const startIndex = (pageNum - 1) * itemsPerPage;
							const endIndex = Math.min(startIndex + itemsPerPage, data.result.length);

							for (let i = startIndex; i < endIndex; i++) {
								const video = data.result[i];
								let list_item = $('<li class="nsz-cloudflare-stream-modal-item" data-video-id="' + video.uid + '"><img src="' + video.thumbnail + '" alt="' + video.meta.name + '"><div>File: ' + video.meta.name + ' <br />Duration: ' + video.duration + 'sec <br />Uploaded: ' + video.uploaded + '<br /><button style="margin-right: 1.25rem" class="button-primary nsz-select-video">Select</button><button class="button-secondary nsz-delete-video">Delete</button></div></li>');

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
							const pageButton = $(`<button class="button-secondary page-button ${i === currentPage ? 'active' : ''}">${i}</button>`);
							pageButton.on('click', function (e) {

								e.preventDefault();
								currentPage = i;
								displayVideosForPage(currentPage);
								paginationContainer.find('.page-button').removeClass('active');
								$(this).addClass('active');
							});
							paginationContainer.append(pageButton);
						}

						// Display first page and add pagination controls
						displayVideosForPage(currentPage);
						video_list.after(paginationContainer);

					}

					cfs_wrap.find('.nsz-cloudflare-stream-modal').attr('open', true);
				},
				error: function (data) {
					console.log(data);
					error_area.html('Error fetching existing videos from Cloudflare.').show();
				}
			});
		});


		$('.nsz-cloudflare-stream-clear-video').on('click', function (e) {
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

		$('.nsz-cloudflare-stream-file').on('change', function (e) {

			let file = e.target.files[0];
			let video_id = null;
			let cfs_wrap = $(this).closest('.cloudflare-stream-wrapper');

			let hls_input = cfs_wrap.find('.data-hls');
			let dash_input = cfs_wrap.find('.data-dash');
			let thumbnail_input = cfs_wrap.find('.data-thumbnail');
			let preview_input = cfs_wrap.find('.data-preview');
			let filename_input = cfs_wrap.find('.data-filename');
			let video_details = cfs_wrap.find('.cloudflare-video-details');
			let filename_display = cfs_wrap.find('.data-filename-display');
			let error_area = cfs_wrap.find('.cloudflare-stream-error');
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

			let upload = new tus.Upload(file, {
				endpoint: 'https://api.cloudflare.com/client/v4/accounts/' + nsz_cloudflare_stream.account_id + '/stream',
				//endpoint: '/wp-json/nsz-cloudflare-stream/url?size='+file.size,
				retryDelays: [0, 3000, 5000, 10000, 20000],
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
					error_area.html('Failed connecting to Cloudflare stream: ' + error).show();
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
			});

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

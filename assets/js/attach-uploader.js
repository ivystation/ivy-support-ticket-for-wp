/**
 * Ivy Support Ticket — 첨부 업로더 (presign → R2 직접 PUT → attachments JSON 누적)
 *
 * 입력 셀렉터:
 *   - input[type=file] (multiple) — 사용자가 선택하는 파일
 *   - <ul> 진행 표시 영역
 *   - <input type=hidden name=attachments> — 누적된 첨부 JSON (배열)
 *
 * 사용:
 *   IvyST.AttachUploader.bind({
 *     fileInput: '#ivy-st-attach-input',
 *     listEl:    '#ivy-st-attach-list',
 *     dataInput: '#ivy-st-attach-data',
 *     maxItems:  5,
 *   });
 *
 * 의존: jQuery, IVY_ST 글로벌(ajaxUrl, nonce, i18n).
 */
/* global IVY_ST, jQuery */
(function ($) {
	'use strict';

	if (!window.IvyST) window.IvyST = {};

	var MAX_FILE_SIZE = 10 * 1024 * 1024;
	var ALLOWED_MIME = [
		'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
		'application/pdf',
		'text/plain', 'text/csv',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/zip', 'application/x-zip-compressed'
	];

	function humanSize(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / 1024 / 1024).toFixed(1) + ' MB';
	}

	/**
	 * 파일 한 개를 처리한다.
	 * 1) admin-ajax presign 호출 → presigned PUT URL
	 * 2) fetch PUT (raw body)
	 * 3) attachments[] 누적 + UI 업데이트
	 */
	function handleFile(state, file) {
		// 클라이언트 사전 검증 — 서버에서도 동일 검증을 수행하지만 UX 개선 목적.
		if (file.size > MAX_FILE_SIZE) {
			renderRow(state, file, 'error', IVY_ST.i18n.attachTooLarge || '파일이 10MB를 초과합니다.');
			return;
		}
		if (file.type && ALLOWED_MIME.indexOf(file.type) < 0) {
			renderRow(state, file, 'error', IVY_ST.i18n.attachBadType || '허용되지 않는 파일 형식입니다.');
			return;
		}
		if (state.items.length >= state.maxItems) {
			renderRow(state, file, 'error', (IVY_ST.i18n.attachTooMany || '첨부는 최대 ${n}개입니다.').replace('${n}', state.maxItems));
			return;
		}

		var $row = renderRow(state, file, 'pending', IVY_ST.i18n.attachUploading || '업로드 중...');

		$.post(IVY_ST.ajaxUrl, {
			action:   'ivy_st_presign',
			_wpnonce: IVY_ST.nonce,
			filename: file.name,
			mimeType: file.type || 'application/octet-stream',
			fileSize: file.size
		})
			.done(function (resp) {
				if (!resp || !resp.success || !resp.data || !resp.data.presignedUrl) {
					var msg = (resp && resp.data && resp.data.message) || (IVY_ST.i18n.genericError || '업로드 준비에 실패했습니다.');
					updateRowState($row, 'error', msg);
					return;
				}

				var put = new XMLHttpRequest();
				put.open('PUT', resp.data.presignedUrl, true);
				if (file.type) {
					put.setRequestHeader('Content-Type', file.type);
				}
				put.upload.onprogress = function (evt) {
					if (evt.lengthComputable) {
						var pct = Math.round(evt.loaded / evt.total * 100);
						updateRowState($row, 'pending', pct + '%');
					}
				};
				put.onload = function () {
					if (put.status >= 200 && put.status < 300) {
						state.items.push({
							r2Key:    resp.data.r2Key,
							fileName: file.name,
							fileSize: file.size,
							mimeType: file.type || 'application/octet-stream'
						});
						state.$dataInput.val(JSON.stringify(state.items));
						updateRowState($row, 'success', IVY_ST.i18n.attachDone || '완료');
					} else {
						updateRowState($row, 'error', 'R2 PUT HTTP ' + put.status);
					}
				};
				put.onerror = function () {
					updateRowState($row, 'error', (IVY_ST.i18n.attachR2Cors || 'R2 업로드 실패. 사이트 도메인의 R2 CORS 등록을 확인하세요.'));
				};
				put.send(file);
			})
			.fail(function (xhr) {
				updateRowState($row, 'error', (IVY_ST.i18n.genericError || '요청 실패') + ' (HTTP ' + (xhr && xhr.status ? xhr.status : '0') + ')');
			});
	}

	function renderRow(state, file, kind, msg) {
		var $li = $('<li class="ivy-st-attach-item"></li>')
			.append($('<span class="ivy-st-attach-name"></span>').text(file.name))
			.append($('<span class="ivy-st-attach-size"></span>').text(' (' + humanSize(file.size) + ')'))
			.append($('<span class="ivy-st-attach-state"></span>'));
		state.$listEl.append($li);
		updateRowState($li, kind, msg);
		return $li;
	}

	function updateRowState($row, kind, msg) {
		$row.removeClass('is-pending is-success is-error').addClass('is-' + kind);
		$row.find('.ivy-st-attach-state').text(msg || '');
	}

	function bind(opts) {
		var $fileInput = $(opts.fileInput);
		var $listEl    = $(opts.listEl);
		var $dataInput = $(opts.dataInput);
		if (!$fileInput.length || !$listEl.length || !$dataInput.length) {
			return null;
		}

		var state = {
			$fileInput: $fileInput,
			$listEl:    $listEl,
			$dataInput: $dataInput,
			maxItems:   opts.maxItems || 5,
			items:      []
		};

		$dataInput.val('[]');

		$fileInput.on('change', function () {
			var files = this.files;
			for (var i = 0; i < files.length; i++) {
				handleFile(state, files[i]);
			}
			// 같은 파일을 다시 선택할 수 있도록 input value 초기화.
			$fileInput.val('');
		});

		return {
			reset: function () {
				state.items = [];
				$dataInput.val('[]');
				$listEl.empty();
			}
		};
	}

	window.IvyST.AttachUploader = { bind: bind };
})(jQuery);

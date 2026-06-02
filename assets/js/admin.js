/* Kashiwazaki SEO ProfilePage Maker - Admin JS
 * UI/UX 15 件改修版
 */
(function ($) {
	'use strict';

	$(function () {
		initTabs();
		initTabBadges();
		initImageFields();
		initBlocks();
		initRepeaters();
		initRepeaterSummary();
		initSlugPreview();
		initStickyBar();
		initDirtyForm();
		initCopyButtons();
		initEEATScoreColor();
		initFieldFilledState();
		initRecommendedMarks();
		initSettingsDirty();
		initSettingsPreview();
		initInlineCopyables();
		initFaqSearchExpand();
	});

	/* ===================================================================
	 * Tabs (T7: バッジ更新)
	 * =================================================================== */
	function initTabs() {
		var $nav = $('.ksppm-tab-nav');
		if (!$nav.length) return;
		$nav.find('a').on('click', function (e) {
			e.preventDefault();
			var target = $(this).attr('href');
			$nav.find('a').removeClass('active');
			$(this).addClass('active');
			$('.ksppm-tab-panel').removeClass('active');
			$(target).addClass('active');
			// LocalStorage で最後のタブを記憶 (T1 補強)
			try { localStorage.setItem('ksppm_last_tab', target); } catch (e) {}
		});
		// 復元
		try {
			var last = localStorage.getItem('ksppm_last_tab');
			if (last && $(last).length) {
				$nav.find('a[href="' + last + '"]').trigger('click');
			}
		} catch (e) {}
	}

	/* タブバッジ (T7): 入力済 / 総数を計算してバッジ更新 */
	function initTabBadges() {
		$('.ksppm-tab-panel').each(function () {
			var $panel = $(this);
			var panelId = $panel.attr('id');
			var $badge = $('.ksppm-tab-nav a[href="#' + panelId + '"] .ksppm-tab-badge');
			if (!$badge.length) return;
			updateBadge($panel, $badge);
			// 入力変化でも更新
			$panel.on('input change', 'input, textarea, select', function () {
				updateBadge($panel, $badge);
			});
		});
	}
	function updateBadge($panel, $badge) {
		var total = 0, filled = 0;
		$panel.find('.ksppm-field').each(function () {
			var $f = $(this);
			// repeater 内のサブフィールドはカウントしない (親 field のみ)
			if ($f.closest('.ksppm-repeater-row').length) return;
			total++;
			if (isFieldFilled($f)) filled++;
		});
		$badge.text(filled + '/' + total);
		$badge.removeClass('ksppm-tab-badge--filled ksppm-tab-badge--partial');
		if (filled === total && total > 0) $badge.addClass('ksppm-tab-badge--filled');
		else if (filled > 0) $badge.addClass('ksppm-tab-badge--partial');
	}
	function isFieldFilled($field) {
		// (custom blocks): hidden JSON をパースして items>0 のブロックがあれば filled
		var $blocksJson = $field.find('.ksppm-blocks-json');
		if ($blocksJson.length) {
			try {
				var arr = JSON.parse($blocksJson.val() || '[]');
				return Array.isArray(arr) && arr.some(function (b) { return b && Array.isArray(b.items) && b.items.length > 0; });
			} catch (e) { return false; }
		}
		var $repeater = $field.find('.ksppm-repeater');
		if ($repeater.length) {
			return $repeater.find('.ksppm-repeater-row').length > 0;
		}
		var $inputs = $field.find('input:not([type=hidden]):not([type=button]):not([type=submit]), textarea, select');
		var hasValue = false;
		$inputs.each(function () {
			var $i = $(this);
			if ($i.attr('type') === 'checkbox') {
				if ($i.is(':checked')) hasValue = true;
			} else if ($i.val() && $i.val().trim() !== '') {
				hasValue = true;
			}
		});
		// 隠れた image hidden input
		var $img = $field.find('input[type=hidden][name$="_thumbnail_id"], .ksppm-image-field input[type=hidden]');
		if ($img.length && $img.val() && $img.val() !== '0') hasValue = true;
		return hasValue;
	}

	/* ===================================================================
	 * Image fields (T6: 統一・空状態改善)
	 * =================================================================== */
	function initImageFields() {
		$(document).on('click', '.ksppm-select-image, .ksppm-image-preview', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $field = $btn.closest('.ksppm-image-field');
			if (!$field.length) return;
			var $input = $field.find('input[type="hidden"]').first();
			var $preview = $field.find('.ksppm-image-preview');
			var $unset = $field.find('.ksppm-unset-image');
			var $meta = $field.find('.ksppm-image-meta');

			var frame = wp.media({
				title: (window.KSPPM && KSPPM.i18n && KSPPM.i18n.selectImg) ? KSPPM.i18n.selectImg : 'Select image',
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$input.val(attachment.id);
				var imgUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				// (v0.0.26-beta + F5 fix): jQuery 要素生成 + .attr() 経由で DOM XSS 耐性を強化 (.html() で URL 文字列連結を回避)
				$preview.empty().append($('<img>', { src: imgUrl, alt: '' }));
				if ($meta.length) {
					$meta.text(attachment.filename + ' (' + (attachment.width || '?') + '×' + (attachment.height || '?') + ')');
				}
				$unset.show();
				// U3/U13 修正: 画像変更も change イベントで filled/dirty 検出を発火
				$input.trigger('change');
			});
			frame.open();
		});

		$(document).on('click', '.ksppm-unset-image', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $field = $(this).closest('.ksppm-image-field');
			var $input = $field.find('input[type="hidden"]').first();
			$input.val('');
			$field.find('.ksppm-image-preview').html(emptyImageHTML());
			$field.find('.ksppm-image-meta').text('');
			$(this).hide();
			// U3/U13 修正: 画像解除も change で filled/dirty 検出
			$input.trigger('change');
		});
	}
	function emptyImageHTML() {
		var label = (window.KSPPM && KSPPM.i18n && KSPPM.i18n.imgEmpty) ? KSPPM.i18n.imgEmpty : 'クリックして画像を選択';
		return '<div class="ksppm-image-empty"><span>' + label + '</span></div>';
	}

	/* ===================================================================
	 * Custom Blocks (任意名称ブロック: 2階層リピーター)
	 * max_input_vars 対策: UI状態を単一 hidden JSON にシリアライズして送信。
	 * =================================================================== */
	function initBlocks() {
		var i18n = (window.KSPPM && KSPPM.i18n) || {};
		function t(k, d) { return i18n[k] || d; }
		// F1: 属性コンテキスト(value="...")へ連結するため " と ' もエスケープ (属性 breakout XSS 防止)
		function esc(s) {
			return String(s == null ? '' : s)
				.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
		}

		$('.ksppm-blocks').each(function () {
			var $wrap = $(this);
			var $json = $wrap.find('.ksppm-blocks-json');
			var $list = $wrap.find('.ksppm-blocks-list');
			var maxBlocks = parseInt($wrap.data('max-blocks'), 10) || 20;
			var maxItems = parseInt($wrap.data('max-items'), 10) || 50;

			var state = [];
			try { var p = JSON.parse($json.val() || '[]'); if (Array.isArray(p)) state = p; } catch (e) { state = []; }
			// 正規化
			state = state.map(function (b) {
				b = b || {};
				return {
					id: typeof b.id === 'string' ? b.id : '',
					title: typeof b.title === 'string' ? b.title : '',
					items: Array.isArray(b.items) ? b.items.map(function (it) {
						it = it || {};
						return {
							heading: typeof it.heading === 'string' ? it.heading : '',
							description: typeof it.description === 'string' ? it.description : '',
							url: typeof it.url === 'string' ? it.url : '',
							image: parseInt(it.image, 10) || 0,
							// 既存画像のサムネ URL (PHP が付与)。保存時は serialize() で破棄、描画プレビュー用に保持。
							_imgUrl: typeof it._imgUrl === 'string' ? it._imgUrl : ''
						};
					}) : []
				};
			});

			function serialize() {
				// _imgUrl 等の UI 専用キーを除き、id/title/items{heading,description,url,image} のみ送信
				var clean = state.map(function (b) {
					return {
						id: b.id || '',
						title: b.title || '',
						items: (b.items || []).map(function (it) {
							return {
								heading: it.heading || '',
								description: it.description || '',
								url: it.url || '',
								image: parseInt(it.image, 10) || 0
							};
						})
					};
				});
				$json.val(JSON.stringify(clean));
				$json.trigger('change'); // dirty 検出/バッジ更新と連携
			}

			function imageMarkup(it, bi, ii) {
				var has = it.image > 0;
				return '<div class="ksppm-block-image" data-bi="' + bi + '" data-ii="' + ii + '">' +
					'<div class="ksppm-block-image-preview">' + (has && it._imgUrl ? '<img src="' + esc(it._imgUrl) + '" alt="">' : '<span class="ksppm-block-image-empty">' + esc(t('imgEmpty', 'クリックして画像を選択')) + '</span>') + '</div>' +
					'<button type="button" class="button button-small ksppm-block-img-select">' + esc(t('selectImg', '画像を選択')) + '</button>' +
					'<button type="button" class="button button-small ksppm-block-img-unset"' + (has ? '' : ' hidden') + '>' + esc(t('unset', '解除')) + '</button>' +
					'</div>';
			}
			function itemMarkup(it, bi, ii) {
				return '<div class="ksppm-block-item" data-bi="' + bi + '" data-ii="' + ii + '">' +
					'<div class="ksppm-block-item-fields">' +
						'<label class="ksppm-block-f"><span>' + esc(t('fHeading', '見出し')) + '</span><input type="text" class="ksppm-bi-heading regular-text" value="' + esc(it.heading) + '"></label>' +
						'<label class="ksppm-block-f"><span>' + esc(t('fDescription', '説明')) + '</span><textarea class="ksppm-bi-description" rows="2">' + esc(it.description) + '</textarea></label>' +
						'<label class="ksppm-block-f"><span>' + esc(t('fUrl', 'URL')) + '</span><input type="url" class="ksppm-bi-url regular-text" value="' + esc(it.url) + '"></label>' +
						'<label class="ksppm-block-f"><span>' + esc(t('fImage', '画像')) + '</span>' + imageMarkup(it, bi, ii) + '</label>' +
					'</div>' +
					'<button type="button" class="button-link ksppm-block-item-remove" title="' + esc(t('itemRemove', '項目を削除')) + '">×</button>' +
					'</div>';
			}
			function blockMarkup(b, bi) {
				var items = b.items.map(function (it, ii) { return itemMarkup(it, bi, ii); }).join('');
				return '<div class="ksppm-block" data-bi="' + bi + '">' +
					'<div class="ksppm-block-head">' +
						'<input type="text" class="ksppm-block-title regular-text" placeholder="' + esc(t('blockTitlePh', '例: 公開中のツール')) + '" value="' + esc(b.title) + '" aria-label="' + esc(t('blockTitle', 'ブロック名')) + '">' +
						'<button type="button" class="button button-small ksppm-block-remove">' + esc(t('blockRemove', 'ブロックを削除')) + '</button>' +
					'</div>' +
					'<div class="ksppm-block-items">' + items + '</div>' +
					'<button type="button" class="button button-link ksppm-block-item-add">+ ' + esc(t('itemAdd', '項目を追加')) + '</button>' +
					'</div>';
			}
			function render() {
				$list.html(state.map(function (b, bi) { return blockMarkup(b, bi); }).join(''));
				serialize();
			}

			// 入力 → state 更新 (再描画せずフォーカス保持)
			$wrap.on('input', '.ksppm-block-title', function () { state[+$(this).closest('.ksppm-block').data('bi')].title = this.value; serialize(); });
			$wrap.on('input', '.ksppm-bi-heading, .ksppm-bi-description, .ksppm-bi-url', function () {
				var $it = $(this).closest('.ksppm-block-item');
				var bi = +$it.data('bi'), ii = +$it.data('ii');
				if (!state[bi] || !state[bi].items[ii]) return;
				if ($(this).hasClass('ksppm-bi-heading')) state[bi].items[ii].heading = this.value;
				else if ($(this).hasClass('ksppm-bi-description')) state[bi].items[ii].description = this.value;
				else state[bi].items[ii].url = this.value;
				serialize();
			});
			// 構造変更 → 再描画
			$wrap.on('click', '.ksppm-blocks-add', function () {
				if (state.length >= maxBlocks) { window.alert(t('blockMaxReached', '上限')); return; }
				state.push({ id: '', title: '', items: [{ heading: '', description: '', url: '', image: 0 }] });
				render();
			});
			$wrap.on('click', '.ksppm-block-remove', function () {
				state.splice(+$(this).closest('.ksppm-block').data('bi'), 1); render();
			});
			$wrap.on('click', '.ksppm-block-item-add', function () {
				var bi = +$(this).closest('.ksppm-block').data('bi');
				if (state[bi].items.length >= maxItems) { window.alert(t('itemMaxReached', '上限')); return; }
				state[bi].items.push({ heading: '', description: '', url: '', image: 0 }); render();
			});
			$wrap.on('click', '.ksppm-block-item-remove', function () {
				var $it = $(this).closest('.ksppm-block-item');
				state[+$it.data('bi')].items.splice(+$it.data('ii'), 1); render();
			});
			// 画像選択 (wp.media)
			$wrap.on('click', '.ksppm-block-img-select', function () {
				var $box = $(this).closest('.ksppm-block-image');
				var bi = +$box.data('bi'), ii = +$box.data('ii');
				var frame = wp.media({ multiple: false, library: { type: 'image' } });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					if (!state[bi] || !state[bi].items[ii]) return;
					state[bi].items[ii].image = a.id;
					state[bi].items[ii]._imgUrl = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
					render();
				});
				frame.open();
			});
			$wrap.on('click', '.ksppm-block-img-unset', function () {
				var $box = $(this).closest('.ksppm-block-image');
				var bi = +$box.data('bi'), ii = +$box.data('ii');
				if (state[bi] && state[bi].items[ii]) { state[bi].items[ii].image = 0; state[bi].items[ii]._imgUrl = ''; }
				render();
			});

			render();
		});
	}

	/* ===================================================================
	 * Repeaters (T5: 折り畳み + 行サマリー)
	 * =================================================================== */
	function initRepeaters() {
		$('.ksppm-repeater').each(function () {
			var $rep = $(this);
			$rep.find('.ksppm-repeater-rows').sortable({
				handle: '.ksppm-row-handle',
				placeholder: 'ui-sortable-placeholder',
				update: function () { reindexRows($rep)(); refreshRepeaterEmpty($rep); }
			});
			refreshRepeaterEmpty($rep);
			refreshRowNumbers($rep);
		});

		$(document).on('click', '.ksppm-repeater-add', function (e) {
			e.preventDefault();
			var $rep = $(this).closest('.ksppm-repeater');
			var $rows = $rep.find('.ksppm-repeater-rows');
			var $tmpl = $rep.find('.ksppm-repeater-template').html();
			if (!$tmpl) return;
			var idx = $rows.find('.ksppm-repeater-row').length;
			var html = $tmpl.replace(/__INDEX__/g, idx);
			$rows.append(html);
			refreshRepeaterEmpty($rep);
			refreshRowNumbers($rep);
			// 新規行は最初から開いた状態
			$rows.find('.ksppm-repeater-row').last().find('details').attr('open', 'open');
			// U3/U13: 行追加で filled/dirty 検出を直接更新 (delegated handler は input/textarea/select のみ対象のため)
			notifyRepeaterChange($rep);
		});

		$(document).on('click', '.ksppm-repeater-remove', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $rep = $(this).closest('.ksppm-repeater');
			$(this).closest('.ksppm-repeater-row').remove();
			reindexRows($rep)();
			refreshRepeaterEmpty($rep);
			refreshRowNumbers($rep);
			// U3/U13: 行削除で filled/dirty 検出を直接更新
			notifyRepeaterChange($rep);
		});
	}

	/**
	 * U3/U13 補完: repeater の構造変化を dirty/filled/badge 検出系へ直接通知
	 * (delegated handler は input/textarea/select のみ対象のため、div の change は届かない)
	 */
	function notifyRepeaterChange($rep) {
		var $field = $rep.closest('.ksppm-field');
		var $form = $('#ksppm-edit-form');
		// filled 更新
		if ($field.length) updateFieldFilled($field);
		// dirty 検出: hidden input にプログラム的に change 発火 (delegated handler の input セレクタにヒット)
		if ($form.length) {
			$form.find('input[name="post_id"]').trigger('change');
		}
		// タブバッジ更新: panel に再計算を要求
		var $panel = $rep.closest('.ksppm-tab-panel');
		if ($panel.length) {
			var panelId = $panel.attr('id');
			var $badge = $('.ksppm-tab-nav a[href="#' + panelId + '"] .ksppm-tab-badge');
			if ($badge.length) updateBadge($panel, $badge);
		}
	}

	function reindexRows($rep) {
		return function () {
			var name = $rep.data('name');
			$rep.find('.ksppm-repeater-row').each(function (i) {
				$(this).attr('data-index', i);
				$(this).find('input, textarea, select').each(function () {
					var n = $(this).attr('name');
					if (!n) return;
					var newN = n.replace(new RegExp('^' + escapeReg(name) + '\\[(\\d+|__INDEX__)\\]'), name + '[' + i + ']');
					$(this).attr('name', newN);
				});
			});
		};
	}

	function refreshRepeaterEmpty($rep) {
		var hasRows = $rep.find('.ksppm-repeater-row').length > 0;
		var $empty = $rep.find('.ksppm-repeater-empty');
		if (hasRows) $empty.hide();
		else $empty.show();
		// U4 修正: 行追加時に .ksppm-repeater--empty class を外す/付与
		// (CSS で .ksppm-repeater--empty .ksppm-repeater-rows { display:none } されているため必須)
		$rep.toggleClass('ksppm-repeater--empty', !hasRows);
	}

	function refreshRowNumbers($rep) {
		$rep.find('.ksppm-repeater-row').each(function (i) {
			$(this).find('.ksppm-row-num').text('#' + (i + 1));
		});
	}

	/* 行サマリーの自動更新 (先頭フィールド値を summary に表示) */
	function initRepeaterSummary() {
		$(document).on('input change', '.ksppm-repeater-row input, .ksppm-repeater-row textarea', function () {
			var $row = $(this).closest('.ksppm-repeater-row');
			updateRowSummary($row);
		});
		// 初期表示
		$('.ksppm-repeater-row').each(function () {
			updateRowSummary($(this));
		});
	}
	function updateRowSummary($row) {
		var $summary = $row.find('.ksppm-row-summary');
		if (!$summary.length) return;
		// 先頭の text input または textarea の値
		var val = '';
		$row.find('input[type="text"], input[type="email"], input[type="url"], textarea').each(function () {
			if (!val && $(this).val() && $(this).val().trim() !== '') {
				val = $(this).val().trim();
				return false; // break
			}
		});
		// 60 文字で切る
		if (val.length > 60) val = val.substring(0, 60) + '…';
		$summary.text(val);
	}

	/* ===================================================================
	 * Slug live preview (T15)
	 * =================================================================== */
	function initSlugPreview() {
		var $title = $('#ksppm_title');
		var $slug = $('#ksppm_slug');
		var $previewBox = $('.ksppm-slug-preview');
		var $preview = $previewBox.find('.ksppm-slug-preview-url');
		if (!$slug.length || !$preview.length) return;
		var $prefix = $('#ksppm_url_prefix');
		// B1: prefix select を反映できるよう home root (prefix 無しの home_url) を基準に組み立てる。
		var homeRoot = $previewBox.data('home-root') || $previewBox.data('home-url') || '';
		var $homeCode = $('.ksppm-slug-home');

		function homeBase() {
			// prefix select があればその選択値で /{prefix}/ を付与、無ければ初期 data-home-url を使う。
			if ($prefix.length) {
				var p = ($prefix.val() || '').replace(/^\/+|\/+$/g, '');
				return homeRoot + (p ? p + '/' : '');
			}
			return $previewBox.data('home-url') || homeRoot;
		}
		function update() {
			var base = homeBase();
			var val = $slug.val().trim();
			if (!val) val = sanitizeTitle($title.val() || '');
			if (!val) val = '...';
			$preview.text(base + val + '/');
			if ($homeCode.length) $homeCode.text(base);
		}
		$slug.on('input', update);
		$title.on('input', function () {
			if (!$slug.val()) update();
		});
		$prefix.on('change', update); // B1: prefix 変更で即時プレビュー更新
		update();
	}
	function sanitizeTitle(s) {
		return s.toLowerCase()
			.replace(/[^\w\s\-]/g, '')
			.replace(/\s+/g, '-')
			.replace(/^-+|-+$/g, '')
			.substring(0, 60);
	}

	/* ===================================================================
	 * Sticky bar (T3) + 保存時刻表示
	 * =================================================================== */
	function initStickyBar() {
		var $bar = $('.ksppm-sticky-bar');
		if (!$bar.length) return;
		// 何もしない (CSS で sticky 化済み、JS は dirty form と連携のみ)
	}

	/* ===================================================================
	 * Dirty form detection (T11 + U13: 初期状態は正直に)
	 * =================================================================== */
	function initDirtyForm() {
		var $form = $('#ksppm-edit-form');
		if (!$form.length) return;
		var isNew = $form.data('is-new') === '1' || $form.data('is-new') === 1;
		var isDirty = false;
		var initialSnapshot = $form.serialize();
		var $status = $('.ksppm-sticky-bar__status');

		function updateStatus() {
			$status.removeClass('ksppm-sticky-bar__status--dirty ksppm-sticky-bar__status--unsaved');
			if (isDirty) {
				$status.addClass('ksppm-sticky-bar__status--dirty');
				$status.text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.dirtyChanges) ? KSPPM.i18n.dirtyChanges : '未保存の変更があります');
			} else if (isNew) {
				$status.addClass('ksppm-sticky-bar__status--unsaved');
				$status.text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.unsaved) ? KSPPM.i18n.unsaved : '未作成');
			} else {
				$status.text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.saved) ? KSPPM.i18n.saved : '保存済み');
			}
		}

		$form.on('input change', 'input, textarea, select', function () {
			var current = $form.serialize();
			var newDirty = (current !== initialSnapshot);
			if (newDirty !== isDirty) {
				isDirty = newDirty;
				updateStatus();
			}
		});

		$form.on('submit', function () {
			isDirty = false;
			window.removeEventListener('beforeunload', beforeUnloadHandler);
		});

		var beforeUnloadHandler = function (e) {
			if (isDirty) {
				e.preventDefault();
				e.returnValue = (window.KSPPM && KSPPM.i18n && KSPPM.i18n.leaveConfirm) ? KSPPM.i18n.leaveConfirm : '入力内容が保存されていません。ページを離れますか?';
				return e.returnValue;
			}
		};
		window.addEventListener('beforeunload', beforeUnloadHandler);
		updateStatus();
	}

	/* ===================================================================
	 * Copy buttons (T15 のサブ機能)
	 * =================================================================== */
	function initCopyButtons() {
		$(document).on('click', '.ksppm-copy-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var target = $btn.data('copy-target');
			var text = target ? $(target).text() : $btn.prev().text();
			if (!text) return;
			try {
				navigator.clipboard.writeText(text).then(function () {
					$btn.addClass('copied').text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.copied) ? KSPPM.i18n.copied : 'コピー済');
					setTimeout(function () { $btn.removeClass('copied').text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.copy) ? KSPPM.i18n.copy : 'コピー'); }, 1500);
				});
			} catch (err) {
				// fallback: 一時 textarea
				var $ta = $('<textarea>').val(text).appendTo('body').select();
				try { document.execCommand('copy'); } catch (e2) {}
				$ta.remove();
				$btn.text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.copied) ? KSPPM.i18n.copied : 'コピー済').addClass('copied');
				setTimeout(function () { $btn.removeClass('copied').text((window.KSPPM && KSPPM.i18n && KSPPM.i18n.copy) ? KSPPM.i18n.copy : 'コピー'); }, 1500);
			}
		});
	}

	/* ===================================================================
	 * E-E-A-T スコア色変更 (T13)
	 * =================================================================== */
	function initEEATScoreColor() {
		$('.ksppm-eeat-score progress').each(function () {
			var v = parseInt($(this).attr('value'), 10);
			if (v >= 80) $(this).addClass('high');
			else if (v < 40) $(this).addClass('low');
		});
	}

	/* ===================================================================
	 * U3: 入力済 / 未入力 視覚差を JS で動的更新
	 * =================================================================== */
	function initFieldFilledState() {
		$('.ksppm-field').each(function () {
			var $f = $(this);
			updateFieldFilled($f);
			$f.on('input change', 'input, textarea, select', function () {
				updateFieldFilled($f);
			});
		});
	}
	function updateFieldFilled($field) {
		var filled = isFieldFilled($field);
		var prev = $field.hasClass('ksppm-field--filled');
		if (filled === prev) return;
		$field.toggleClass('ksppm-field--filled', filled);
		var $label = $field.find('> label');
		if (filled) {
			if (!$label.find('.ksppm-field-filled-mark').length) {
				$label.append(' <span class="ksppm-field-filled-mark" aria-label="' + ((window.KSPPM && KSPPM.i18n && KSPPM.i18n.filledMark) ? KSPPM.i18n.filledMark : '入力済') + '">✓</span>');
			}
		} else {
			$label.find('.ksppm-field-filled-mark').remove();
		}
	}

	/* ===================================================================
	 * U7: recommended フィールドのハイライト (新規時のみ)
	 * =================================================================== */
	function initRecommendedMarks() {
		var $form = $('#ksppm-edit-form');
		var isNew = $form.data('is-new') === '1' || $form.data('is-new') === 1;
		if (!isNew) return; // U7 修正: 新規時のみハイライト
		var recommended = [
			'ksppm_full_name', 'ksppm_job_title', 'ksppm_description',
			'ksppm_main_image', '_thumbnail_id', 'ksppm_same_as'
		];
		recommended.forEach(function (name) {
			var $el = $('[name="' + name + '"]').first();
			if (!$el.length) return;
			var $field = $el.closest('.ksppm-field');
			if (!$field.length) {
				$field = $el.closest('.postbox');
			}
			$field.addClass('ksppm-field--recommended');
		});
	}

	function escapeReg(s) {
		return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}
})(jQuery);

/* ===================================================================
 * v1.0.12: 設定タブ T9 ダーティバナー / T10 inline notice 連携
 * =================================================================== */
(function ($) {
	'use strict';

	window.initSettingsDirty = function () {
		var $form = $('form[data-ksppm-dirty-form]');
		if (!$form.length) return;
		var $banner = $form.find('[data-ksppm-dirty-banner]');
		var initial = $form.serialize();
		function check() {
			var dirty = ($form.serialize() !== initial);
			$banner.prop('hidden', !dirty);
		}
		$form.on('input change', 'input, select, textarea', check);
		$form.on('submit', function () { $banner.prop('hidden', true); });
	};

	/* T12 メインプロフィール プレビューリンク */
	window.initSettingsPreview = function () {
		var $select = $('[data-ksppm-preview-select]');
		if (!$select.length) return;
		var map;
		try { map = JSON.parse($select.attr('data-ksppm-preview-select')); } catch (e) { return; }
		var $wrap = $('[data-ksppm-preview-target]');
		if (!$wrap.length) return;
		var $link = $wrap.find('a');
		var $status = $wrap.find('.ksppm-field__preview-status');
		var i18n = (window.KSPPM && KSPPM.i18n) || {};
		var statusLabels = { publish: i18n.statusPublish || '公開', draft: i18n.statusDraft || '下書き', private: i18n.statusPrivate || '非公開', pending: i18n.statusPending || '保留中' };
		function update() {
			var id = parseInt($select.val(), 10);
			var info = id && map[id] ? map[id] : null;
			if (info && info.url) {
				$link.attr('href', info.url);
				$status.text(statusLabels[info.status] || info.status).attr('data-status', info.status);
				$wrap.prop('hidden', false);
			} else {
				$wrap.prop('hidden', true);
			}
		}
		$select.on('change', update);
		update();
	};

	/* T4 使い方タブ inline コピー (<code class="ksppm-copyable" data-copy="...">) */
	window.initInlineCopyables = function () {
		var $items = $('.ksppm-copyable');
		if (!$items.length) return;
		$items.attr({ role: 'button', tabindex: '0', title: (window.KSPPM && KSPPM.i18n && KSPPM.i18n.clickToCopy) ? KSPPM.i18n.clickToCopy : 'クリックでコピー' });
		function copy(el) {
			var text = el.getAttribute('data-copy') || el.textContent;
			if (!text) return;
			var done = function () {
				el.classList.add('is-copied');
				setTimeout(function () { el.classList.remove('is-copied'); }, 1500);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done, function () { fallback(text, done); });
			} else {
				fallback(text, done);
			}
		}
		function fallback(text, done) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'absolute'; ta.style.left = '-9999px';
			document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); done(); } catch (e) {}
			document.body.removeChild(ta);
		}
		$(document).on('click', '.ksppm-copyable', function () { copy(this); });
		$(document).on('keydown', '.ksppm-copyable', function (e) {
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); copy(this); }
		});
	};

	/* (v0.0.20-beta): URL プレフィックス ライブプレビュー
	 * 設定画面で url_prefix 入力中に /home/{prefix}/{slug}/ をリアルタイム反映
	 */
	$(function () {
		var $input = $('[data-ksppm-url-prefix]');
		if (!$input.length) return;
		var $preview = $('[data-ksppm-url-prefix-preview]');
		var home = $input.attr('data-ksppm-home') || '/';
		var $rootCheckbox = $('#ksppm_root_rewrite_enabled');
		function normalize(raw) {
			if (typeof raw !== 'string') return '';
			// 前後 whitespace を trim してから処理 (PHP の trim(" \t\n\r\0\x0B/") と仕様一致)
			var p = raw.replace(/^[\s/]+|[\s/]+$/g, '').replace(/\/+/g, '/');
			// PHP normalize_url_prefix は strtolower するので JS preview も lowercase で揃える
			p = p.toLowerCase();
			if (!p) return '';
			if (!/^[a-z0-9\-_/]+$/i.test(p)) return null;
			var reserved = ['feed','rdf','rss','rss2','atom','trackback','embed','page','comments','wp-sitemap.xml','wp-json','wp-login.php','wp-admin','robots.txt','sitemap.xml','favicon.ico','xmlrpc.php','wp-cron.php','wp-content','wp-includes','oembed'];
			var segs = p.split('/');
			for (var i = 0; i < segs.length; i++) {
				if (reserved.indexOf(segs[i].toLowerCase()) !== -1) return null;
			}
			return p;
		}
		function update() {
			var raw = $input.val() || '';
			var p = normalize(raw);
			if (p === null) {
				$preview.text('(無効な文字または予約 slug)').css('color', '#d63638');
				return;
			}
			$preview.css('color', '');
			if (p === '') {
				$preview.text(home + '?ksppm_profile={slug}/');
			} else {
				$preview.text(home + p + '/{slug}/');
			}
			if ($rootCheckbox.length) {
				if (p === '') {
					$rootCheckbox.prop('disabled', false);
				} else {
					$rootCheckbox.prop('disabled', true).prop('checked', false);
				}
			}
		}
		$input.on('input change keyup', update);
	});

	/* T5 補助: Ctrl+F / Cmd+F で FAQ 全展開 + Escape で初期状態復帰 (usage 画面で DOM に存在するときのみ bind) */
	window.initFaqSearchExpand = function () {
		var $faqs = $('.ksppm-usage-faq__item');
		if (!$faqs.length) return; // usage 画面以外 (= DOM に FAQ なし) では listener を貼らない
		document.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F')) {
				$faqs.each(function () { this.open = true; });
			} else if (e.key === 'Escape') {
				$faqs.each(function (i) { this.open = (i === 0); });
			}
		});
	};
})(jQuery);

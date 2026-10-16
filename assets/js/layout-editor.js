/**
 * Nested email layout editor (section > row > column > content).
 * Fields stay editable; only the handle is draggable.
 */
(function ($) {
	'use strict';

	var ns = '.ottermail-wrap';
	var catalog = (window.ottermailLayout && window.ottermailLayout.types) ? window.ottermailLayout.types : {};
	var i18n = (window.ottermailLayout && window.ottermailLayout.i18n) ? window.ottermailLayout.i18n : {};
	var WIDTHS = ['100', '50-50', '33-33-33', '70-30', '30-70', '60-40', '40-60'];
	var active = { sectionId: '', rowId: '', colIndex: 0, blockId: '' };
	var dragType = null;
	var dragId = null;

	function uid() {
		return 'b' + Math.random().toString(36).slice(2, 12);
	}

	function $json() {
		return $(ns + ' #ottermail_layout_json');
	}

	function $canvas() {
		return $(ns + ' #ottermail-builder-canvas');
	}

	function attr($el, name) {
		return String($el.attr(name) || '');
	}

	function walk(blocks, cb) {
		(blocks || []).forEach(function (block) {
			cb(block);
			if (block.children) {
				walk(block.children, cb);
			}
			if (block.columns) {
				block.columns.forEach(function (col) {
					walk(col.children || [], cb);
				});
			}
		});
	}

	function readBlocks() {
		try {
			var parsed = JSON.parse($json().val() || '[]');
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function writeBlocks(blocks) {
		$json().val(JSON.stringify(blocks));
		syncLegacy(blocks);
	}

	function firstOf(blocks, type, key) {
		var found = '';
		walk(blocks, function (block) {
			if (!found && block.type === type && block.data && block.data[key]) {
				found = block.data[key];
			}
		});
		return found;
	}

	function syncLegacy(blocks) {
		var $form = $(ns + ' #ottermail-template-form');
		if (!$form.length) {
			return;
		}
		$form.find('[name="logo_url"]').val(firstOf(blocks, 'logo', 'url'));
		$form.find('[name="heading"]').val(firstOf(blocks, 'heading', 'text'));
		$form.find('[name="body"]').val(firstOf(blocks, 'text', 'html'));
		$form.find('[name="cta_label"]').val(firstOf(blocks, 'button', 'label'));
		$form.find('[name="cta_url"]').val(firstOf(blocks, 'button', 'url'));
		$form.find('[name="footer"]').val(firstOf(blocks, 'footer', 'html'));
		$form.find('[name="product_ids"]').val(firstOf(blocks, 'products', 'product_ids'));
		var hasProducts = false;
		walk(blocks, function (block) {
			if (block.type === 'products') {
				hasProducts = true;
			}
		});
		$form.find('[name="show_products"]').val(hasProducts ? '1' : '0');
	}

	function columnCount(widths) {
		return String(widths || '100').split('-').length;
	}

	function emptyColumns(widths) {
		var cols = [];
		var n = columnCount(widths);
		var i;
		for (i = 0; i < n; i += 1) {
			cols.push({ children: [] });
		}
		return cols;
	}

	function syncRowColumns(row) {
		var widths = (row.data && row.data.widths) ? row.data.widths : '100';
		var n = columnCount(widths);
		var cols = Array.isArray(row.columns) ? row.columns.slice() : [];
		while (cols.length < n) {
			cols.push({ children: [] });
		}
		if (cols.length > n) {
			cols.slice(n).forEach(function (extra) {
				(extra.children || []).forEach(function (child) {
					cols[n - 1].children.push(child);
				});
			});
			cols = cols.slice(0, n);
		}
		row.data = row.data || {};
		row.data.widths = widths;
		row.data.columns = n;
		row.columns = cols;
	}

	function syncAllRows(blocks) {
		walk(blocks, function (block) {
			if (block.type === 'row') {
				syncRowColumns(block);
			}
		});
		return blocks;
	}

	function ensureTree(blocks) {
		if (!blocks.length) {
			return blocks;
		}
		var hasSection = blocks.some(function (block) {
			return block.type === 'section';
		});
		if (hasSection) {
			return blocks;
		}
		var section = makeBlock('section');
		var rows = [];
		var loose = [];
		blocks.forEach(function (block) {
			if (block.type === 'row') {
				if (loose.length) {
					var wrap = makeBlock('row', '100');
					wrap.columns[0].children = loose;
					rows.push(wrap);
					loose = [];
				}
				rows.push(block);
			} else {
				loose.push(block);
			}
		});
		if (loose.length) {
			var last = makeBlock('row', '100');
			last.columns[0].children = loose;
			rows.push(last);
		}
		section.children = rows.length ? rows : [makeBlock('row', '100')];
		return [section];
	}

	function makeBlock(type, widths) {
		var meta = catalog[type] || { defaults: {} };
		var block = {
			id: uid(),
			type: type,
			data: $.extend(true, {}, meta.defaults || {})
		};
		if (type === 'row') {
			block.data.widths = widths || block.data.widths || '50-50';
			syncRowColumns(block);
		}
		if (type === 'section') {
			block.children = [makeBlock('row', '100')];
		}
		return block;
	}

	function kindOf(type) {
		if (type === 'section') {
			return 'section';
		}
		if (type === 'row') {
			return 'row';
		}
		return 'content';
	}

	function labelFor(type) {
		return (catalog[type] && catalog[type].label) ? catalog[type].label : type;
	}

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function field(label, html) {
		return '<label class="ottermail-block-field-wrap"><span>' + escapeHtml(label) + '</span>' + html + '</label>';
	}

	function input(type, key, value, placeholder, extraClass) {
		return '<input type="' + type + '" class="ottermail-block-field' + (extraClass ? ' ' + extraClass : '') + '" data-key="' + escapeHtml(key) + '" value="' + escapeHtml(value == null ? '' : value) + '" placeholder="' + escapeHtml(placeholder || '') + '" draggable="false" autocomplete="off">';
	}

	function textarea(key, value, placeholder) {
		return '<textarea class="ottermail-block-field" data-key="' + escapeHtml(key) + '" rows="5" placeholder="' + escapeHtml(placeholder || '') + '" draggable="false">' + escapeHtml(value || '') + '</textarea>';
	}

	function number(key, value, min, max) {
		return '<input type="number" class="ottermail-block-field" data-key="' + escapeHtml(key) + '" value="' + escapeHtml(value == null ? '' : value) + '" min="' + min + '" max="' + max + '" draggable="false">';
	}

	function color(key, value, fallback) {
		var hex = value && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value) ? value : (fallback || '#ffffff');
		if (hex.length === 4) {
			hex = '#' + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2) + hex.charAt(3) + hex.charAt(3);
		}
		return '<input type="color" class="ottermail-block-field ottermail-color" data-key="' + escapeHtml(key) + '" value="' + escapeHtml(hex) + '" draggable="false">';
	}

	function select(key, value, options) {
		var html = '<select class="ottermail-block-field" data-key="' + escapeHtml(key) + '" draggable="false">';
		options.forEach(function (opt) {
			html += '<option value="' + escapeHtml(opt.value) + '"' + (String(opt.value) === String(value) ? ' selected' : '') + '>' + escapeHtml(opt.label) + '</option>';
		});
		html += '</select>';
		return html;
	}

	function checkbox(key, checked, label) {
		return '<label class="ottermail-check"><input type="checkbox" class="ottermail-block-field" data-key="' + escapeHtml(key) + '" value="1"' + (checked ? ' checked' : '') + ' draggable="false"> ' + escapeHtml(label) + '</label>';
	}

	function alignOptions() {
		return [
			{ value: 'left', label: i18n.alignLeft || 'Esquerda' },
			{ value: 'center', label: i18n.alignCenter || 'Centro' },
			{ value: 'right', label: i18n.alignRight || 'Direita' }
		];
	}

	function widthOptions() {
		var labels = i18n.widths || {};
		return WIDTHS.map(function (key) {
			return { value: key, label: labels[key] || key.replace(/-/g, ' / ') };
		});
	}

	function styleGrid(items) {
		return '<div class="ottermail-style-grid">' + items.join('') + '</div>';
	}

	function collectFields($scope) {
		var data = {};
		$scope.find('.ottermail-block-field').each(function () {
			var $field = $(this);
			var key = attr($field, 'data-key');
			if (!key) {
				return;
			}
			if ($field.is(':checkbox')) {
				data[key] = $field.is(':checked') ? '1' : '0';
			} else {
				data[key] = $field.val();
			}
		});
		return data;
	}

	function fieldHtml(block) {
		var d = block.data || {};
		var type = block.type;
		var html = '';

		if (type === 'section') {
			return styleGrid([
				field(i18n.background || 'Fundo', color('background', d.background, '#ffffff')),
				field(i18n.padding || 'Espaçamento (px)', number('padding', d.padding == null ? 24 : d.padding, 0, 80))
			]);
		}

		if (type === 'row') {
			return styleGrid([
				field(i18n.columns || 'Colunas', select('widths', d.widths || '50-50', widthOptions())),
				field(i18n.padding || 'Espaçamento (px)', number('padding', d.padding == null ? 8 : d.padding, 0, 48)),
				field(i18n.background || 'Fundo', color('background', d.background, '#ffffff'))
			]);
		}

		if (type === 'logo' || type === 'image') {
			html += '<div class="ottermail-media-field">';
			html += '<div class="ottermail-media-preview"' + (d.url ? '' : ' hidden') + '><img src="' + escapeHtml(d.url || '') + '" alt=""></div>';
			html += input('url', 'url', d.url, 'https://', 'ottermail-media-url');
			html += '<div class="ottermail-media-actions">';
			html += '<button type="button" class="button ottermail-media-select">' + (i18n.selectImage || 'Selecionar imagem') + '</button> ';
			html += '<button type="button" class="button ottermail-media-remove"' + (d.url ? '' : ' hidden') + '>' + (i18n.removeImage || 'Remover') + '</button>';
			html += '</div></div>';
			if (type === 'image') {
				html += field(i18n.alt || 'Texto alternativo', input('text', 'alt', d.alt, i18n.alt || 'Texto alternativo'));
				html += styleGrid([
					field(i18n.imageWidth || 'Largura (%)', number('width', d.width == null ? 100 : d.width, 20, 100)),
					field(i18n.align || 'Alinhamento', select('align', d.align || 'center', alignOptions()))
				]);
			} else {
				html += styleGrid([
					field(i18n.logoWidth || 'Largura (px)', number('width', d.width == null ? 160 : d.width, 40, 320)),
					field(i18n.align || 'Alinhamento', select('align', d.align || 'center', alignOptions()))
				]);
			}
			return field(i18n.imageUrl || 'URL da imagem', html);
		}

		if (type === 'heading') {
			return field(i18n.heading || 'Título', input('text', 'text', d.text, i18n.heading || 'Título do email'))
				+ styleGrid([
					field(i18n.color || 'Cor', color('color', d.color, '#1d2327')),
					field(i18n.fontSize || 'Tamanho (px)', number('font_size', d.font_size == null ? 24 : d.font_size, 14, 48)),
					field(i18n.align || 'Alinhamento', select('align', d.align || 'center', alignOptions()))
				]);
		}

		if (type === 'text' || type === 'footer') {
			return field(type === 'footer' ? (i18n.footer || 'Rodapé') : (i18n.html || 'Conteúdo'), textarea('html', d.html, type === 'footer' ? (i18n.footer || 'Rodapé') : (i18n.html || 'Texto ou HTML do email')))
				+ styleGrid([
					field(i18n.color || 'Cor', color('color', d.color, type === 'footer' ? '#646970' : '#1d2327')),
					field(i18n.fontSize || 'Tamanho (px)', number('font_size', d.font_size == null ? (type === 'footer' ? 12 : 16) : d.font_size, 10, 24)),
					field(i18n.align || 'Alinhamento', select('align', d.align || (type === 'footer' ? 'center' : 'left'), alignOptions()))
				]);
		}

		if (type === 'button') {
			return field(i18n.buttonLabel || 'Texto do botão', input('text', 'label', d.label, i18n.buttonLabel || 'Texto do botão'))
				+ field(i18n.buttonUrl || 'Link', input('text', 'url', d.url, 'https:// ou {{store.url}}'))
				+ styleGrid([
					field(i18n.buttonBg || 'Fundo do botão', color('background', d.background, '#1d4ed8')),
					field(i18n.buttonColor || 'Cor do texto', color('color', d.color, '#ffffff')),
					field(i18n.fontSize || 'Tamanho (px)', number('font_size', d.font_size == null ? 16 : d.font_size, 12, 24)),
					field(i18n.align || 'Alinhamento', select('align', d.align || 'center', alignOptions()))
				])
				+ checkbox('full_width', d.full_width === 1 || d.full_width === '1', i18n.fullWidth || 'Largura total');
		}

		if (type === 'products') {
			return field(i18n.productIds || 'IDs de produtos', input('text', 'product_ids', d.product_ids, i18n.productIds || 'IDs separados por vírgula'));
		}

		if (type === 'spacer') {
			return field(i18n.spacer || 'Altura (px)', number('height', d.height == null ? 24 : d.height, 8, 160));
		}

		if (type === 'coupon') {
			return field(i18n.coupon || 'Cupom WooCommerce', input('text', 'code', d.code, i18n.couponPlaceholder || 'Código do cupom criado no WooCommerce'))
				+ styleGrid([
					field(i18n.color || 'Cor', color('color', d.color, '#1d4ed8'))
				]);
		}

		if (type === 'divider') {
			return styleGrid([
				field(i18n.color || 'Cor', color('color', d.color, '#e5e7eb'))
			]);
		}

		return '';
	}

	function blockHead(block) {
		var html = '<header class="ottermail-block-head">';
		html += '<span class="ottermail-block-handle" draggable="true" title="' + (i18n.drag || 'Arrastar') + '" aria-label="' + (i18n.drag || 'Arrastar') + '">⋮⋮</span>';
		html += '<strong>' + escapeHtml(labelFor(block.type)) + '</strong>';
		html += '<button type="button" class="ottermail-icon-btn ottermail-block-up" title="' + (i18n.moveUp || 'Subir') + '" aria-label="' + (i18n.moveUp || 'Subir') + '">↑</button>';
		html += '<button type="button" class="ottermail-icon-btn ottermail-block-down" title="' + (i18n.moveDown || 'Descer') + '" aria-label="' + (i18n.moveDown || 'Descer') + '">↓</button>';
		html += '<button type="button" class="ottermail-icon-btn ottermail-block-remove" aria-label="' + (i18n.remove || 'Remover') + '">×</button>';
		html += '</header>';
		return html;
	}

	function contentHtml(block) {
		var html = '<article class="ottermail-block ottermail-block--content" data-id="' + escapeHtml(block.id) + '" data-type="' + escapeHtml(block.type) + '">';
		html += blockHead(block);
		html += '<div class="ottermail-block-settings">' + fieldHtml(block) + '</div>';
		html += '</article>';
		return html;
	}

	function columnHtml(row, col, index) {
		var widths = (row.data && row.data.widths) ? row.data.widths : '100';
		var pct = Math.round(100 / columnCount(widths));
		var parts = String(widths).split('-');
		if (parts[index]) {
			pct = parseInt(parts[index], 10) || pct;
		}
		var html = '<div class="ottermail-column" data-col="' + index + '" style="flex:' + pct + ';max-width:' + pct + '%;">';
		html += '<div class="ottermail-column-label">' + pct + '%</div>';
		html += '<div class="ottermail-dropzone ottermail-dropzone--content" data-drop="content">';
		(col.children || []).forEach(function (child) {
			html += contentHtml(child);
		});
		if (!(col.children || []).length) {
			html += '<p class="ottermail-column-empty">' + (i18n.dropContent || 'Solte conteúdo aqui') + '</p>';
		}
		html += '</div></div>';
		return html;
	}

	function rowHtml(row) {
		syncRowColumns(row);
		var html = '<article class="ottermail-block ottermail-block--row" data-id="' + escapeHtml(row.id) + '" data-type="row">';
		html += blockHead(row);
		html += '<div class="ottermail-block-settings">' + fieldHtml(row) + '</div>';
		html += '<div class="ottermail-row-cols">';
		(row.columns || []).forEach(function (col, index) {
			html += columnHtml(row, col, index);
		});
		html += '</div></article>';
		return html;
	}

	function sectionHtml(section) {
		var html = '<article class="ottermail-block ottermail-block--section" data-id="' + escapeHtml(section.id) + '" data-type="section">';
		html += blockHead(section);
		html += '<div class="ottermail-block-settings">' + fieldHtml(section) + '</div>';
		html += '<div class="ottermail-dropzone ottermail-dropzone--rows" data-drop="rows">';
		(section.children || []).forEach(function (row) {
			html += rowHtml(row);
		});
		if (!(section.children || []).length) {
			html += '<p class="ottermail-column-empty">' + (i18n.dropRow || 'Solte uma linha aqui') + '</p>';
		}
		html += '</div></article>';
		return html;
	}

	function render(blocks) {
		var list = blocks || readBlocks();
		if (!list.length) {
			$canvas().html('<p class="ottermail-builder-empty">' + (i18n.empty || 'Adicione uma seção para começar o layout.') + '</p>');
			return;
		}
		$canvas().html(list.map(sectionHtml).join(''));
		paintActive();
	}

	function collectBlock($block) {
		var type = attr($block, 'data-type');
		var id = attr($block, 'data-id') || uid();
		var data = collectFields($block.children('.ottermail-block-settings'));
		var block = { id: id, type: type, data: data };

		if (type === 'section') {
			block.children = collectList($block.children('.ottermail-dropzone--rows'));
		}
		if (type === 'row') {
			block.columns = [];
			$block.children('.ottermail-row-cols').children('.ottermail-column').each(function () {
				block.columns.push({
					children: collectList($(this).children('.ottermail-dropzone--content'))
				});
			});
		}
		return block;
	}

	function collectList($container) {
		var blocks = [];
		if (!$container || !$container.length) {
			return blocks;
		}
		$container.children('.ottermail-block').each(function () {
			blocks.push(collectBlock($(this)));
		});
		return blocks;
	}

	function collectFromDom() {
		return syncAllRows(collectList($canvas()));
	}

	function persistFromDom() {
		var blocks = collectFromDom();
		if (!blocks.length && !readBlocks().length) {
			return blocks;
		}
		writeBlocks(blocks);
		return blocks;
	}

	function locate(blocks, id) {
		var i;
		var loc;
		var j;
		var kids;
		for (i = 0; i < blocks.length; i += 1) {
			if (blocks[i].id === id) {
				return { list: blocks, index: i, block: blocks[i] };
			}
			if (blocks[i].children) {
				loc = locate(blocks[i].children, id);
				if (loc) {
					return loc;
				}
			}
			if (blocks[i].columns) {
				for (j = 0; j < blocks[i].columns.length; j += 1) {
					kids = blocks[i].columns[j].children || [];
					loc = locate(kids, id);
					if (loc) {
						return loc;
					}
				}
			}
		}
		return null;
	}

	function findSection(blocks, id) {
		var i;
		for (i = 0; i < blocks.length; i += 1) {
			if (blocks[i].type === 'section' && (!id || blocks[i].id === id)) {
				return blocks[i];
			}
		}
		for (i = blocks.length - 1; i >= 0; i -= 1) {
			if (blocks[i].type === 'section') {
				return blocks[i];
			}
		}
		return null;
	}

	function targetColumn(blocks) {
		var section = findSection(blocks, active.sectionId) || findSection(blocks, '');
		if (!section) {
			section = makeBlock('section');
			blocks.push(section);
		}
		if (!section.children || !section.children.length) {
			section.children = [makeBlock('row', '100')];
		}
		var row = null;
		(section.children || []).forEach(function (item) {
			if (item.type === 'row' && (!active.rowId || item.id === active.rowId)) {
				row = item;
			}
		});
		if (!row) {
			row = section.children[section.children.length - 1];
		}
		if (!row || row.type !== 'row') {
			row = makeBlock('row', '100');
			section.children.push(row);
		}
		syncRowColumns(row);
		var idx = active.colIndex || 0;
		if (idx < 0 || idx >= row.columns.length) {
			idx = row.columns.length - 1;
		}
		return row.columns[idx];
	}

	function insertAt(type, hint) {
		var blocks = persistFromDom();
		var block = makeBlock(type);
		var kind = kindOf(type);
		var section;
		var index;

		if (!blocks.length && kind !== 'section') {
			blocks.push(makeBlock('section'));
		}

		if (kind === 'section') {
			index = hint && typeof hint.index === 'number' ? hint.index : blocks.length;
			blocks.splice(index, 0, block);
			active.sectionId = block.id;
			active.rowId = block.children && block.children[0] ? block.children[0].id : '';
			active.colIndex = 0;
			active.blockId = block.id;
		} else if (kind === 'row') {
			section = findSection(blocks, hint && hint.sectionId ? hint.sectionId : active.sectionId) || findSection(blocks, '');
			if (!section) {
				section = makeBlock('section');
				section.children = [];
				blocks.push(section);
			}
			section.children = section.children || [];
			index = hint && typeof hint.index === 'number' ? hint.index : section.children.length;
			section.children.splice(index, 0, block);
			active.sectionId = section.id;
			active.rowId = block.id;
			active.colIndex = 0;
			active.blockId = block.id;
		} else {
			var col = targetColumn(blocks);
			index = hint && typeof hint.index === 'number' ? hint.index : col.children.length;
			col.children.splice(index, 0, block);
			active.blockId = block.id;
		}

		writeBlocks(blocks);
		render(blocks);
		window.setTimeout(function () {
			var $block = $canvas().find('.ottermail-block[data-id="' + block.id + '"]');
			$block.find('.ottermail-block-field').first().trigger('focus');
		}, 0);
	}

	function removeById(id) {
		var blocks = persistFromDom();
		var loc = locate(blocks, id);
		if (!loc) {
			return;
		}
		loc.list.splice(loc.index, 1);
		writeBlocks(blocks);
		render(blocks);
	}

	function moveBy(id, delta) {
		var blocks = persistFromDom();
		var loc = locate(blocks, id);
		if (!loc) {
			return;
		}
		var to = loc.index + delta;
		if (to < 0 || to >= loc.list.length) {
			return;
		}
		var item = loc.list.splice(loc.index, 1)[0];
		loc.list.splice(to, 0, item);
		writeBlocks(blocks);
		render(blocks);
	}

	function moveItem(blocks, id, destList, destIndex) {
		var loc = locate(blocks, id);
		if (!loc || !destList) {
			return blocks;
		}
		var item = loc.list.splice(loc.index, 1)[0];
		if (loc.list === destList && destIndex > loc.index) {
			destIndex -= 1;
		}
		if (destIndex < 0) {
			destIndex = 0;
		}
		if (destIndex > destList.length) {
			destIndex = destList.length;
		}
		destList.splice(destIndex, 0, item);
		return blocks;
	}

	function siblingIndex($block) {
		return $block.parent().children('.ottermail-block').index($block);
	}

	function dropHint(e, kind) {
		var $targetBlock = $(e.target).closest('.ottermail-block');
		var $column = $(e.target).closest('.ottermail-column');
		var $section = $(e.target).closest('.ottermail-block[data-type="section"]');
		var $row = $(e.target).closest('.ottermail-block[data-type="row"]');
		var hint = { kind: kind, index: null, sectionId: '', rowId: '', colIndex: 0 };

		if (kind === 'section') {
			if ($targetBlock.length && attr($targetBlock, 'data-type') === 'section') {
				hint.index = siblingIndex($targetBlock);
				if (e.pageY >= $targetBlock.offset().top + ($targetBlock.outerHeight() / 2)) {
					hint.index += 1;
				}
			} else {
				hint.index = $canvas().children('.ottermail-block').length;
			}
			return hint;
		}

		if (kind === 'row') {
			hint.sectionId = $section.length ? attr($section, 'data-id') : active.sectionId;
			if ($targetBlock.length && attr($targetBlock, 'data-type') === 'row') {
				hint.index = siblingIndex($targetBlock);
				if (e.pageY >= $targetBlock.offset().top + ($targetBlock.outerHeight() / 2)) {
					hint.index += 1;
				}
			}
			return hint;
		}

		if ($column.length) {
			hint.sectionId = $section.length ? attr($section, 'data-id') : '';
			hint.rowId = $row.length ? attr($row, 'data-id') : '';
			hint.colIndex = parseInt($column.attr('data-col') || '0', 10);
			if ($targetBlock.length && $targetBlock.closest('.ottermail-column')[0] === $column[0] && attr($targetBlock, 'data-type') !== 'row' && attr($targetBlock, 'data-type') !== 'section') {
				hint.index = siblingIndex($targetBlock);
				if (e.pageY >= $targetBlock.offset().top + ($targetBlock.outerHeight() / 2)) {
					hint.index += 1;
				}
			}
		}
		return hint;
	}

	function applyHintToActive(hint) {
		if (hint.sectionId) {
			active.sectionId = hint.sectionId;
		}
		if (hint.rowId) {
			active.rowId = hint.rowId;
		}
		if (typeof hint.colIndex === 'number') {
			active.colIndex = hint.colIndex;
		}
	}

	function paintActive() {
		$canvas().find('.is-active, .is-active-drop').removeClass('is-active is-active-drop');
		if (active.sectionId) {
			$canvas().find('.ottermail-block[data-id="' + active.sectionId + '"]').first().addClass('is-active');
		}
		if (active.rowId) {
			$canvas().find('.ottermail-block[data-id="' + active.rowId + '"]').first().addClass('is-active');
		}
		if (active.rowId) {
			$canvas().find('.ottermail-block[data-id="' + active.rowId + '"] .ottermail-column[data-col="' + active.colIndex + '"]').addClass('is-active-drop');
		} else {
			$canvas().find('.ottermail-column').last().addClass('is-active-drop');
		}
	}

	function setActiveFromEl($el) {
		var $block = $el.closest('.ottermail-block');
		var $col = $el.closest('.ottermail-column');
		var $row = $el.closest('.ottermail-block[data-type="row"]');
		var $section = $el.closest('.ottermail-block[data-type="section"]');
		active.blockId = $block.length ? attr($block, 'data-id') : '';
		active.sectionId = $section.length ? attr($section, 'data-id') : '';
		active.rowId = $row.length ? attr($row, 'data-id') : '';
		active.colIndex = $col.length ? parseInt($col.attr('data-col') || '0', 10) : 0;
		paintActive();
	}

	function clearDropMarks() {
		$canvas().find('.ottermail-block').removeClass('is-dragging is-drop-before is-drop-after');
		$canvas().find('.ottermail-column, .ottermail-dropzone').removeClass('is-drop-target');
	}

	$(function () {
		if (!$(ns + ' #ottermail-builder').length) {
			return;
		}

		var initial = ensureTree(syncAllRows(readBlocks()));
		if (initial.length) {
			writeBlocks(initial);
			render(initial);
		} else {
			render([]);
		}

		$(document).on('click', ns + ' .ottermail-palette-item', function (e) {
			e.preventDefault();
			insertAt(attr($(this), 'data-type'));
		});

		$(document).on('click', ns + ' .ottermail-block, ' + ns + ' .ottermail-column', function (e) {
			setActiveFromEl($(e.target));
		});

		$(document).on('click', ns + ' .ottermail-block-remove', function (e) {
			e.preventDefault();
			e.stopPropagation();
			removeById(attr($(this).closest('.ottermail-block'), 'data-id'));
		});

		$(document).on('click', ns + ' .ottermail-block-up', function (e) {
			e.preventDefault();
			e.stopPropagation();
			moveBy(attr($(this).closest('.ottermail-block'), 'data-id'), -1);
		});

		$(document).on('click', ns + ' .ottermail-block-down', function (e) {
			e.preventDefault();
			e.stopPropagation();
			moveBy(attr($(this).closest('.ottermail-block'), 'data-id'), 1);
		});

		$(document).on('input change', ns + ' .ottermail-block-field', function () {
			var $field = $(this);
			var blocks = persistFromDom();
			if (attr($field, 'data-key') === 'widths') {
				syncAllRows(blocks);
				writeBlocks(blocks);
				render(blocks);
			}
		});

		$(ns + ' #ottermail-template-form').on('submit ottermail-persist-layout', persistFromDom);

		$(document).on('dragstart', ns + ' .ottermail-palette-item', function (e) {
			dragType = attr($(this), 'data-type');
			dragId = null;
			e.originalEvent.dataTransfer.setData('text/plain', 'type:' + dragType);
			e.originalEvent.dataTransfer.effectAllowed = 'copy';
		});

		$(document).on('dragstart', ns + ' .ottermail-block-handle', function (e) {
			var $block = $(this).closest('.ottermail-block');
			dragId = attr($block, 'data-id');
			dragType = null;
			e.originalEvent.dataTransfer.setData('text/plain', 'block:' + dragId);
			e.originalEvent.dataTransfer.effectAllowed = 'move';
			$block.addClass('is-dragging');
			e.stopPropagation();
		});

		$(document).on('dragend', ns + ' .ottermail-block-handle, ' + ns + ' .ottermail-palette-item', function () {
			clearDropMarks();
			dragId = null;
			dragType = null;
		});

		$(document).on('dragover', ns + ' #ottermail-builder-canvas, ' + ns + ' .ottermail-block, ' + ns + ' .ottermail-column, ' + ns + ' .ottermail-dropzone', function (e) {
			e.preventDefault();
			e.stopPropagation();
			e.originalEvent.dataTransfer.dropEffect = dragId ? 'move' : 'copy';
			clearDropMarks();
			var $column = $(e.target).closest('.ottermail-column');
			var $block = $(e.target).closest('.ottermail-block');
			if ($column.length && kindOf(dragType || '') === 'content') {
				$column.addClass('is-drop-target');
			}
			if ($block.length) {
				var mid = $block.offset().top + ($block.outerHeight() / 2);
				$block.addClass(e.pageY < mid ? 'is-drop-before' : 'is-drop-after');
			}
		});

		$(document).on('drop', ns + ' #ottermail-builder-canvas', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var type = dragType;
			var id = dragId;
			var kind = kindOf(type || '');
			if (id) {
				var $moved = $canvas().find('.ottermail-block[data-id="' + id + '"]');
				kind = kindOf(attr($moved, 'data-type'));
			}
			var hint = dropHint(e, kind);
			applyHintToActive(hint);

			if (type) {
				insertAt(type, hint);
			} else if (id) {
				var blocks = persistFromDom();
				var loc = locate(blocks, id);
				if (loc) {
					var dest = loc.list;
					var destIndex = hint.index;
					if (kind === 'section') {
						dest = blocks;
					} else if (kind === 'row') {
						var section = findSection(blocks, hint.sectionId) || findSection(blocks, '');
						if (section) {
							section.children = section.children || [];
							dest = section.children;
						}
					} else {
						active.sectionId = hint.sectionId || active.sectionId;
						active.rowId = hint.rowId || active.rowId;
						active.colIndex = typeof hint.colIndex === 'number' ? hint.colIndex : active.colIndex;
						dest = targetColumn(blocks).children;
					}
					if (typeof destIndex !== 'number') {
						destIndex = dest.length;
					}
					blocks = moveItem(blocks, id, dest, destIndex);
					writeBlocks(blocks);
					render(blocks);
				}
			}
			clearDropMarks();
			dragType = null;
			dragId = null;
		});
	});
})(jQuery);

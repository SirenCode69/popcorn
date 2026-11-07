/* Popcorn mega menu editor blocks by SirenCode. */
(function (blocks, element, blockEditor, components, data) {
    const el = element.createElement;
    const menuIcon = el('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round' }, el('rect', { x: 3, y: 3, width: 18, height: 18, rx: 3 }), el('path', { d: 'M7 8h10M7 12h10M7 16h6' }));
    const gridIcon = el('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round' }, el('rect', { x: 3, y: 3, width: 7, height: 7, rx: 1 }), el('rect', { x: 14, y: 3, width: 7, height: 7, rx: 1 }), el('rect', { x: 3, y: 14, width: 7, height: 7, rx: 1 }), el('rect', { x: 14, y: 14, width: 7, height: 7, rx: 1 }));
    const placeholder = function (title, description) {
        return el('div', blockEditor.useBlockProps({ className: 'pc-mega-editor-block' }), el(components.Placeholder, { label: title }, el('p', null, description)));
    };
    blocks.registerBlockType('popcorn/menu-links', {
        apiVersion: 3,
        title: 'بخش‌های فهرست پاپکورن',
        icon: menuIcon,
        description: 'زیرآیتم‌های همان گزینه در فهرست اصلی وردپرس را نمایش می‌دهد.',
        category: 'widgets',
        supports: { html: false },
        edit: function () { return placeholder('بخش‌های فهرست', 'پیوندهای زیرمجموعهٔ این گزینه از نمایش ← فهرست‌ها خوانده می‌شوند.'); },
        save: function () { return null; }
    });
    blocks.registerBlockType('popcorn/genre-grid', {
        apiVersion: 3,
        title: 'ژانرهای پاپکورن',
        icon: gridIcon,
        description: 'ژانرهای انتخاب‌شده در فهرست یا ژانرهای آثار منتشرشده را نمایش می‌دهد.',
        category: 'widgets',
        supports: { html: false },
        edit: function () { return placeholder('ژانرهای پاپکورن', 'ژانرهای انتخاب‌شده در فهرست اصلی، یا ژانرهای پرکاربرد، در سایت نمایش داده می‌شوند.'); },
        save: function () { return null; }
    });
    blocks.registerBlockType('popcorn/mega-menu-item', {
        apiVersion: 3,
        title: 'مگامنوی پاپکورن',
        icon: menuIcon,
        description: 'یک مگامنوی بلوکی به فهرست ناوبری اضافه می‌کند.',
        category: 'design',
        parent: ['core/navigation'],
        attributes: { label: { type: 'string', default: 'مگامنو' }, menuSlug: { type: 'string', default: '' } },
        supports: { html: false },
        edit: function (props) {
            const menus = data.useSelect(function (select) {
                return select('core').getEntityRecords('postType', 'popcorn_mega_menu', { per_page: 100, status: 'publish' }) || [];
            }, []);
            const options = [{ label: 'انتخاب چیدمان', value: '' }].concat(menus.map(function (menu) {
                return { label: menu.title.rendered, value: menu.slug };
            }));
            return el(element.Fragment, null,
                el(blockEditor.InspectorControls, null,
                    el(components.PanelBody, { title: 'تنظیمات مگامنو', initialOpen: true },
                        el(components.TextControl, { label: 'عنوان', value: props.attributes.label, onChange: function (value) { props.setAttributes({ label: value }); } }),
                        el(components.SelectControl, { label: 'چیدمان بلوکی', value: props.attributes.menuSlug, options: options, onChange: function (value) { props.setAttributes({ menuSlug: value }); } })
                    )
                ),
                el('div', blockEditor.useBlockProps(), props.attributes.label || 'مگامنو')
            );
        },
        save: function () { return null; }
    });
})(wp.blocks, wp.element, wp.blockEditor, wp.components, wp.data);

const htmlToElem = (html) => wp.element.RawHTML({ children: html });

const settings = window.wc.wcSettings.getSetting('cobru_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Cobru for WC', 'cobru');
const Content = () => {
    return htmlToElem(window.wp.htmlEntities.decodeEntities(settings.description || ''));
};
const Block_Gateway = {
    name: 'cobru',
    label: htmlToElem(label),
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
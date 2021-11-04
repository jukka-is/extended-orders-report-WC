// Import SCSS entry file so that webpack picks up changes
import './index.scss';


import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

const addshippingZoneFilters = (filters) => {
    return [
        {
            label: __('Shipping zone', 'dev-blog-example'),
            staticParams: [],
            param: 'zone_id',
            showFilters: () => true,
            defaultValue: '-1',
            filters: [...(wcSettings.shippingZones || [])],
        },
        ...filters,
    ];
};

addFilter(
    'woocommerce_admin_orders_report_filters',
    'dev-blog-example',
    addshippingZoneFilters
);


const addTableColumn = reportTableData => {
    if ('orders' !== reportTableData.endpoint) {
        return reportTableData;
    }

    const newHeaders = [
        ...reportTableData.headers,
        {
            label: "Country",
            key: 'coutry',
        },

    ];
    const newRows = reportTableData.rows.map((row, index) => {
        const item = reportTableData.items.data[index];
        const newRow = [
            ...row,
            {
                display: item.extended_info.customer.country,
                value: item.extended_info.customer.country,
            },

        ];
        return newRow;
    });

    reportTableData.headers = newHeaders;
    reportTableData.rows = newRows;

    return reportTableData;
};

addFilter('woocommerce_admin_report_table', 'dev-blog-example', addTableColumn);


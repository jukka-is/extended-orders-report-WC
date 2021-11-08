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
        {
            label: "Zone",
            key: 'shipping_zone',
        },
        {
            label: "Shipping",
            key: 'shipping_cost',
        },

    ];
    const newRows = reportTableData.rows.map((row, index) => {
        const item = reportTableData.items.data[index];
        console.log(item);
        const newRow = [
            ...row,
            {
                display: item.extended_info.customer.country,
                value: item.extended_info.customer.country,
            },
            {
                display: item.shipping_zone,
                value: item.shipping_zone,
            },
            {
                display: formatter.format(item.shipping_cost),
                value: item.shipping_cost,
            },

        ];
        return newRow;
    });

    reportTableData.headers = newHeaders;
    reportTableData.rows = newRows;

    return reportTableData;
};

addFilter('woocommerce_admin_report_table', 'dev-blog-example', addTableColumn);


const formatter = new Intl.NumberFormat('fi-FI', {
    style: 'currency',
    currency: 'EUR'
})


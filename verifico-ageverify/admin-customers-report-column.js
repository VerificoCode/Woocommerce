/**
 * Adds an "Age Verification Status" column to the WooCommerce Analytics >
 * Customers report table.
 *
 * The underlying data (merchant_age_verified) is added to each row's REST
 * payload server-side via the woocommerce_rest_prepare_report_customers
 * filter in index.php. This filter only arranges how that data is
 * displayed as a table column.
 */
wp.hooks.addFilter(
	'woocommerce_admin_report_table',
	'verifico-ageverify/customers-age-verification-column',
	( tableData ) => {
		if ( tableData.endpoint !== 'customers' ) {
			return tableData;
		}

		tableData.headers.push( {
			label: 'Age Verification Status',
			key: 'merchant_age_verified',
		} );

		tableData.rows = tableData.rows.map( ( row, index ) => {
			const customer = tableData.items.data[ index ];
			const isVerified = customer && customer.merchant_age_verified === 'true';
			const display = isVerified ? 'Verified' : 'Not verified';

			return [
				...row,
				{
					display,
					value: display,
				},
			];
		} );

		return tableData;
	}
);

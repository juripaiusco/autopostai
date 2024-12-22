export function __currency(data, currency, sign = false) {

    let currencyFormat;
    let out = '';

    switch (currency) {

        case 'EUR':

            currencyFormat = Intl.NumberFormat('it', {
                style: 'currency',
                currency: 'EUR',
            });

            break;

    }

    if (sign === true) {

        out = (data >= 0 ? '+' : '-') + ' ';
        data = Math.abs(data);
    }

    out = out + currencyFormat.format(data)

    return out;

}

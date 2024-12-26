export function __date(dateString, format = '') {

    const date = new Date(dateString);
    // Then specify how you want your dates to be formatted
    let y = new Intl.DateTimeFormat('default', { year: 'numeric' }).format(date);
    let m = new Intl.DateTimeFormat('default', { month: '2-digit' }).format(date);
    let d = new Intl.DateTimeFormat('default', { day: '2-digit' }).format(date);
    let h = new Intl.DateTimeFormat('default', { hour: '2-digit' }).format(date);
    let i = new Intl.DateTimeFormat('default', { minute: '2-digit' }).format(date);
    let s = new Intl.DateTimeFormat('default', { second: '2-digit' }).format(date);

    if (i < 10) {
        i = '0' + i;
    }

    if (s < 10) {
        s = '0' + s;
    }

    if (dateString != null) {

        switch (format) {
            case 'day':
                return d + '/' + m + '/' + y;
                break;
            case 'hour':
                return h + ':' + i;
                break;
            case 'date':
                return y + '-' + m + '-' + d;
                break;
            case 'y':
                return y;
                break;
            case 'm':
                return m;
                break;
            case 'n':
                return parseInt(m);
                break;
            case 'datestring':
                return y + '-' + m + '-' + d + ' ' + h + ':' + i + ':' + s;
                break;
            default:
                return d + '/' + m + '/' + y + ' ' + h + ':' + i + ':' + s;
        }

    }

    return null;
}

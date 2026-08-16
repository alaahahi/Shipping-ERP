/**
 * Temporary: chassis numbers cannot contain the letter O.
 * Import/new input replaces O with 0. Existing cars keep O and show a list warning.
 * Remove this file with ChassisLetterO.php and the list badges when the check is dropped.
 */
export function chassisHasLetterO(value) {
    return /O/.test(String(value || '').toUpperCase());
}

export function replaceChassisLetterO(value) {
    return String(value || '').replace(/O/gi, '0');
}

export function sanitizeChassisNumber(value) {
    return replaceChassisLetterO(String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, ''));
}

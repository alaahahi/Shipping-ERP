import { initFlowbite } from 'flowbite';

export function bootFlowbite() {
    initFlowbite();
}

export const fbLabel = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';

export const fbInput =
    'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-teal-600 focus:border-teal-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-teal-500 dark:focus:border-teal-500';

export const fbButton =
    'text-white bg-teal-700 hover:bg-teal-800 focus:ring-4 focus:outline-none focus:ring-teal-300 font-medium rounded-lg text-sm w-full px-5 py-2.5 text-center inline-flex items-center justify-center gap-2 dark:bg-teal-600 dark:hover:bg-teal-700 dark:focus:ring-teal-800 disabled:opacity-50 disabled:pointer-events-none';

export const fbSuccessButton =
    'text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm w-full px-5 py-2.5 text-center inline-flex items-center justify-center gap-2 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 disabled:opacity-50 disabled:pointer-events-none';

export const fbDangerButton =
    'text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm w-full px-5 py-2.5 text-center inline-flex items-center justify-center gap-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800 disabled:opacity-50 disabled:pointer-events-none';

export const fbGhostButton =
    'text-gray-700 bg-gray-50 border border-gray-200 hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-3 py-2 inline-flex items-center gap-2 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-700';

export const fbLink = 'text-sm font-medium text-teal-700 hover:underline dark:text-teal-400';

export const fbAlertSuccess =
    'p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400';

export const fbCheckbox =
    'w-4 h-4 text-teal-700 bg-gray-100 border-gray-300 rounded focus:ring-2 focus:ring-teal-300 dark:bg-gray-700 dark:border-gray-500 dark:focus:ring-teal-600 dark:ring-offset-gray-800 dark:checked:bg-teal-600 dark:checked:border-teal-500';

require('../components/_reset.css');

import {default as createTabs} from '../components/tabs';
import {addEventListener} from '../components/_event';

addEventListener(document, 'DOMContentLoaded', function() {
    createTabs();
});

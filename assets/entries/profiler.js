import '../components/_vars.css';
import '../components/_reset.css';
import '../components/_basics.scss';
import {default as createTabs} from '../components/tabs';
import {addEventListener} from '../components/_event';

addEventListener(document, 'DOMContentLoaded', function() {
    createTabs();
});

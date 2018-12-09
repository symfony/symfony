import '../components/_vars.css';
import '../components/_reset.css';
import '../components/_basics.scss';
import {ready} from '../components/_event';
import {default as createTabs} from '../components/tabs';

ready(function() {
    createTabs();
});

import '../../components/_vars.css';
import '../../components/_reset.css';
import '../../components/_utils.css';
import './_page.scss';
import {ready} from '../../components/_event';
import {default as createTabs} from '../../components/tabs';

ready(function() {
    createTabs();
});

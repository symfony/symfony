import
    './pizza/index.js';
import Popcorn from './popcorn.js';

import('./imported_async.js').then(() => {
    console.log('async import done');
});

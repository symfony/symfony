'use strict';

class TimelineEngine {
    /**
     * @param  {Theme} theme
     * @param  {Renderer} renderer
     * @param  {Legend} legend
     * @param  {Element} threshold
     * @param  {Object} request
     * @param  {Number} eventHeight
     * @param  {Number} horizontalMargin
     */
    constructor(theme, renderer, legend, threshold, request, eventHeight = 36, horizontalMargin = 10) {
        this.theme = theme;
        this.renderer = renderer;
        this.legend = legend;
        this.threshold = threshold;
        this.request = request;
        this.scale = renderer.width / request.end;
        this.eventHeight = eventHeight;
        this.horizontalMargin = horizontalMargin;
        this.labelY = Math.round(this.eventHeight * 0.48);
        this.periodY = Math.round(this.eventHeight * 0.66);
        this.FqcnMatcher = /\\([^\\]+)$/i;
        this.origin = null;

        this.createEventElements = this.createEventElements.bind(this);
        this.createBackground = this.createBackground.bind(this);
        this.createPeriod = this.createPeriod.bind(this);
        this.render = this.render.bind(this);
        this.renderEvent = this.renderEvent.bind(this);
        this.renderPeriod = this.renderPeriod.bind(this);
        this.onResize = this.onResize.bind(this);
        this.isActive = this.isActive.bind(this);

        this.threshold.addEventListener('change', this.render);
        this.legend.addEventListener('change', this.render);

        window.addEventListener('resize', this.onResize);

        this.createElements();
        this.render();
    }

    onResize() {
        this.renderer.measure();
        this.setScale(this.renderer.width / this.request.end);
    }

    setScale(scale) {
        if (scale !== this.scale) {
            this.scale = scale;
            this.render();
        }
    }

    createElements() {
        this.origin = this.renderer.setFullVerticalLine(this.createBorder(), 0);
        this.renderer.add(this.origin);

        this.request.events
            .filter(event => event.category === 'section')
            .map(this.createBackground)
            .forEach(this.renderer.add);

        this.request.events
            .map(this.createEventElements)
            .forEach(this.renderer.add);
    }

    createBackground(event) {
        const subrequest = event.name === '__section__.child';
        const background = this.renderer.create('rect', subrequest ? 'timeline-subrequest' : 'timeline-border');

        event.elements = Object.assign(event.elements || {}, { background });

        return background;
    }

    createEventElements(event) {
        const { name, category, duration, memory, periods } = event;
        const border = this.renderer.setFullHorizontalLine(this.createBorder(), 0);
        const lines = periods.map(period => this.createPeriod(period, category));
        const label = this.createLabel(this.getShortName(name), duration, memory, periods[0]);
        const title = this.renderer.createTitle(name);
        const group = this.renderer.group([title, border, label].concat(lines), this.theme.getCategoryColor(event.category));

        event.elements = Object.assign(event.elements || {}, { group, label, border });

        this.legend.add(event.category)

        return group;
    }

    createLabel(name, duration, memory, period) {
        const label = this.renderer.createText(name, period.start * this.scale, this.labelY, 'timeline-label');
        const sublabel = this.renderer.createTspan(`  ${duration} ms / ${memory} MiB`, 'timeline-sublabel');

        label.appendChild(sublabel);

        return label;
    }

    createPeriod(period, category) {
        const timeline = this.renderer.createPath(null, 'timeline-period', this.theme.getCategoryColor(category));

        period.draw = category === 'section' ? this.renderer.setSectionLine : this.renderer.setPeriodLine;
        period.elements = Object.assign(period.elements || {}, { timeline });

        return timeline;
    }

    createBorder() {
        return this.renderer.createPath(null, 'timeline-border');
    }

    isActive(event) {
        const { duration, category } = event;

        return duration >= this.threshold.value && this.legend.isActive(category);
    }

    render() {
        const events = this.request.events.filter(this.isActive);
        const width = this.renderer.width + this.horizontalMargin * 2;
        const height = this.eventHeight * events.length;

        // Set view box
        this.renderer.setViewBox(-this.horizontalMargin, 0, width, height);

        // Show 0ms origin
        this.renderer.setFullVerticalLine(this.origin, 0);

        // Render all events
        this.request.events.forEach(event => this.renderEvent(event, events.indexOf(event)));
    }

    renderEvent(event, index) {
        const { name, category, duration, memory, periods, elements } = event;
        const { group, label, border, background } = elements;
        const visible = index >= 0;

        group.setAttribute('visibility', visible ? 'visible' : 'hidden');

        if (background) {
            background.setAttribute('visibility', visible ? 'visible' : 'hidden');

            if (visible) {
                const [min, max] = this.getEventLimits(event);

                this.renderer.setFullRectangle(background, min * this.scale, max * this.scale);
            }
        }

        if (visible) {
            // Position the group
            group.setAttribute('transform', `translate(0, ${index * this.eventHeight})`);

            // Update top border
            this.renderer.setFullHorizontalLine(border, 0);

            // render label and ensure it doesn't escape the viewport
            this.renderLabel(label, event);

            // Update periods
            periods.forEach(this.renderPeriod);
        }
    }

    renderLabel(label, event) {
        const width = this.getLabelWidth(label);
        const [min, max] = this.getEventLimits(event);
        const alignLeft = (min * this.scale) + width <= this.renderer.width;

        label.setAttribute('x', (alignLeft ? min : max) * this.scale);
        label.setAttribute('text-anchor', alignLeft ? 'start' : 'end');
    }

    renderPeriod(period) {
        const { elements, start, duration } = period;

        period.draw(elements.timeline, start * this.scale, this.periodY, Math.max(duration * this.scale, 1));
    }

    getLabelWidth(label) {
        if (typeof label.width === 'undefined') {
            label.width = label.getBBox().width;
        }

        return label.width;
    }

    getEventLimits(event) {
        if (typeof event.limits === 'undefined') {
            const { periods } = event;

            event.limits = [
                periods[0].start,
                periods[periods.length - 1].end
            ];
        }

        return event.limits;
    }

    getShortName(name) {
        const matches = this.FqcnMatcher.exec(name);

        if (matches) {
            return matches[1];
        }

        return name;
    }
}

class Legend {
    constructor(element, theme) {
        this.element = element;
        this.theme = theme;

        this.toggle = this.toggle.bind(this);
        this.createCategory = this.createCategory.bind(this);

        this.categories = [];
        this.theme.getDefaultCategories().forEach(this.createCategory);
    }

    add(category) {
        this.get(category).classList.add('present');
    }

    createCategory(category) {
        const element = document.createElement('button');
        element.className = `timeline-category active`;
        element.style.borderColor = this.theme.getCategoryColor(category);
        element.innerText = category;
        element.value = category;
        element.type = 'button';
        element.addEventListener('click', this.toggle);

        this.element.appendChild(element);

        this.categories.push(element);

        return element;
    }

    toggle(event) {
        event.target.classList.toggle('active');

        this.emit('change');
    }

    isActive(category) {
        return this.get(category).classList.contains('active');
    }

    get(category) {
        return this.categories.find(element => element.value === category) || this.createCategory(category);
    }

    emit(name) {
        this.element.dispatchEvent(new Event(name));
    }

    addEventListener(name, callback) {
        this.element.addEventListener(name, callback);
    }

    removeEventListener(name, callback) {
        this.element.removeEventListener(name, callback);
    }
}

class SvgRenderer {
    /**
     * @param  {SVGElement} element
     */
    constructor(element) {
        this.ns = 'http://www.w3.org/2000/svg';
        this.width = null;
        this.viewBox = {};
        this.element = element;

        this.add = this.add.bind(this);

        this.setViewBox(0, 0, 0, 0);
        this.measure();
    }

    setViewBox(x, y, width, height) {
        this.viewBox = { x, y, width, height };
        this.element.setAttribute('viewBox', `${x} ${y} ${width} ${height}`);
    }

    measure() {
        this.width = this.element.getBoundingClientRect().width;
    }

    add(element) {
        this.element.appendChild(element);
    }

    group(elements, className) {
        const group = this.create('g', className);

        elements.forEach(element => group.appendChild(element));

        return group;
    }

    setHorizontalLine(element, x, y, width) {
        element.setAttribute('d', `M${x},${y} h${width}`);

        return element;
    }

    setVerticalLine(element, x, y, height) {
        element.setAttribute('d', `M${x},${y} v${height}`);

        return element;
    }

    setFullHorizontalLine(element, y) {
        return this.setHorizontalLine(element, this.viewBox.x, y, this.viewBox.width);
    }

    setFullVerticalLine(element, x) {
        return this.setVerticalLine(element, x, this.viewBox.y, this.viewBox.height);
    }

    setFullRectangle(element, min, max) {
        element.setAttribute('x', min);
        element.setAttribute('y', this.viewBox.y);
        element.setAttribute('width', max - min);
        element.setAttribute('height', this.viewBox.height);
    }

    setSectionLine(element, x, y, width, height = 4, markerSize = 6) {
        const totalHeight = height + markerSize;
        const maxMarkerWidth = Math.min(markerSize, width / 2);
        const widthWithoutMarker = Math.max(0, width - (maxMarkerWidth * 2));

        element.setAttribute('d', `M${x},${y + totalHeight} v${-totalHeight} h${width} v${totalHeight} l${-maxMarkerWidth} ${-markerSize} h${-widthWithoutMarker} Z`);
    }

    setPeriodLine(element, x, y, width, height = 4, markerWidth = 2, markerHeight = 4) {
        const totalHeight = height + markerHeight;
        const maxMarkerWidth = Math.min(markerWidth, width);

        element.setAttribute('d', `M${x + maxMarkerWidth},${y + totalHeight} h${-maxMarkerWidth} v${-totalHeight} h${width} v${height} h${maxMarkerWidth-width}Z`);
    }

    createText(content, x, y, className) {
        const element = this.create('text', className);

        element.setAttribute('x', x);
        element.setAttribute('y', y);
        element.textContent = content;

        return element;
    }

    createTspan(content, className) {
        const element = this.create('tspan', className);

        element.textContent = content;

        return element;
    }

    createTitle(content) {
        const element = this.create('title');

        element.textContent = content;

        return element;
    }

    createPath(path = null, className = null, color = null) {
        const element = this.create('path', className);

        if (path) {
            element.setAttribute('d', path);
        }

        if (color) {
            element.setAttribute('fill', color);
        }

        return element;
    }

    create(name, className = null) {
        const element = document.createElementNS(this.ns, name);

        if (className) {
            element.setAttribute('class', className);
        }

        return element;
    }
}

class Theme {
    constructor(element) {
        this.reservedCategoryColors = {
            'default': '#777',
            'section': '#999',
            'event_listener': '#00b8f5',
            'template': '#66cc00',
            'doctrine': '#ff6633',
            'messenger_middleware': '#bdb81e',
            'controller.argument_value_resolver': '#8c5de6',
            'http_client': '#ffa333',
        };

        this.customCategoryColors = [
            '#dbab09', // dark yellow
            '#ea4aaa', // pink
            '#964b00', // brown
            '#22863a', // dark green
            '#0366d6', // dark blue
            '#17a2b8', // teal
        ];

        this.getCategoryColor = this.getCategoryColor.bind(this);
        this.getDefaultCategories = this.getDefaultCategories.bind(this);
    }

    getDefaultCategories() {
        return Object.keys(this.reservedCategoryColors);
    }

    getCategoryColor(category) {
        return this.reservedCategoryColors[category] || this.getRandomColor(category);
    }

    getRandomColor(category) {
        // instead of pure randomness, colors are assigned deterministically based on the
        // category name, to ensure that each custom category always displays the same color
        return this.customCategoryColors[this.hash(category) % this.customCategoryColors.length];
    }

    // copied from https://github.com/darkskyapp/string-hash
    hash(string) {
        var hash = 5381;
        var i = string.length;

        while(i) {
            hash = (hash * 33) ^ string.charCodeAt(--i);
        }

        return hash >>> 0;
    }
}

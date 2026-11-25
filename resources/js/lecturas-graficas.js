const svgNamespace = 'http://www.w3.org/2000/svg';
const palette = {
    contador: '#0d6efd',
    negro: '#343a40',
    cian: '#0dcaf0',
    magenta: '#d63384',
    amarillo: '#ffc107',
    tricolor: '#20c997',
    no_aplica: '#6c757d',
};

function createSvgElement(name, attributes = {}) {
    const element = document.createElementNS(svgNamespace, name);
    for (const [attribute, value] of Object.entries(attributes)) {
        element.setAttribute(attribute, value);
    }
    return element;
}

function drawChart(svg, legend, labels, dates, datasets, percent = false) {
    const width = 900;
    const height = 300;
    const margin = { top: 16, right: 20, bottom: 46, left: 72 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;
    const values = datasets.flatMap((dataset) => dataset.values.filter(Number.isFinite));
    const x = (index) => margin.left + (labels.length < 2 ? plotWidth / 2 : (index / (labels.length - 1)) * plotWidth);

    svg.replaceChildren();
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    legend.replaceChildren();

    if (values.length === 0) {
        const empty = createSvgElement('text', {
            x: width / 2,
            y: height / 2,
            'text-anchor': 'middle',
            fill: 'var(--bs-secondary-color)',
        });
        empty.textContent = percent ? 'No hay niveles registrados' : 'No hay contadores registrados';
        svg.append(empty);
        return;
    }

    let minValue = percent ? 0 : Math.min(...values);
    let maxValue = percent ? 100 : Math.max(...values);
    if (!percent) {
        const padding = maxValue === minValue ? Math.max(1, maxValue * 0.05) : (maxValue - minValue) * 0.08;
        minValue = Math.max(0, minValue - padding);
        maxValue += padding;
    }

    const y = (value) => margin.top + ((maxValue - value) / (maxValue - minValue)) * plotHeight;
    const tickCount = 4;
    for (let tick = 0; tick <= tickCount; tick += 1) {
        const value = minValue + ((maxValue - minValue) * tick) / tickCount;
        const tickY = y(value);
        const line = createSvgElement('line', {
            x1: margin.left,
            x2: width - margin.right,
            y1: tickY,
            y2: tickY,
            stroke: 'var(--bs-border-color)',
        });
        const text = createSvgElement('text', {
            x: margin.left - 10,
            y: tickY + 4,
            'text-anchor': 'end',
            fill: 'var(--bs-secondary-color)',
            'font-size': 12,
        });
        text.textContent = `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(value)}${percent ? '%' : ''}`;
        svg.append(line, text);
    }

    const labelStep = Math.max(1, Math.ceil(labels.length / 6));
    labels.forEach((label, index) => {
        if (index % labelStep !== 0 && index !== labels.length - 1) return;

        const text = createSvgElement('text', {
            x: x(index),
            y: height - 12,
            'text-anchor': 'middle',
            fill: 'var(--bs-secondary-color)',
            'font-size': 12,
        });
        text.textContent = label;
        svg.append(text);
    });

    datasets.forEach((dataset) => {
        const color = palette[dataset.color] || palette.contador;
        let segment = [];

        const drawSegment = () => {
            if (segment.length > 1) {
                const path = createSvgElement('path', {
                    d: segment.map(([pointX, pointY], index) => `${index === 0 ? 'M' : 'L'} ${pointX} ${pointY}`).join(' '),
                    fill: 'none',
                    stroke: color,
                    'stroke-width': 2.5,
                    'stroke-linecap': 'round',
                    'stroke-linejoin': 'round',
                });
                svg.append(path);
            }
            segment = [];
        };

        dataset.values.forEach((value, index) => {
            if (!Number.isFinite(value)) {
                drawSegment();
                return;
            }

            const pointX = x(index);
            const pointY = y(value);
            segment.push([pointX, pointY]);

            const point = createSvgElement('circle', {
                cx: pointX,
                cy: pointY,
                r: 4,
                fill: color,
            });
            const title = createSvgElement('title');
            const formatted = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(value);
            title.textContent = `${dates[index]} · ${dataset.label}: ${formatted}${percent ? '%' : ''}`;
            point.append(title);
            svg.append(point);
        });
        drawSegment();

        const item = document.createElement('span');
        item.className = 'lecturas-chart-legend-item';
        const swatch = document.createElement('span');
        swatch.className = 'lecturas-chart-legend-swatch';
        swatch.style.backgroundColor = color;
        const label = document.createElement('span');
        label.textContent = dataset.label;
        item.append(swatch, label);
        legend.append(item);
    });
}

const dataElement = document.getElementById('datos-grafica-lecturas');
if (dataElement) {
    const data = JSON.parse(dataElement.textContent);
    drawChart(
        document.querySelector('[data-lecturas-chart="contador"]'),
        document.querySelector('[data-chart-legend="contador"]'),
        data.etiquetas,
        data.fechas,
        [{ label: 'Páginas', color: 'contador', values: data.contadores }],
    );
    drawChart(
        document.querySelector('[data-lecturas-chart="niveles"]'),
        document.querySelector('[data-chart-legend="niveles"]'),
        data.etiquetas,
        data.fechas,
        data.niveles,
        true,
    );
}

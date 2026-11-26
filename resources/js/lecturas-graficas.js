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

function drawChart(svg, legend, labels, dates, datasets, percent = false, thresholds = { critico: 10, bajo: 25 }) {
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
    if (percent) {
        svg.append(
            createSvgElement('rect', {
                x: margin.left,
                y: y(thresholds.critico),
                width: plotWidth,
                height: y(0) - y(thresholds.critico),
                fill: 'rgba(220, 53, 69, 0.08)',
            }),
            createSvgElement('rect', {
                x: margin.left,
                y: y(thresholds.bajo),
                width: plotWidth,
                height: y(thresholds.critico) - y(thresholds.bajo),
                fill: 'rgba(255, 193, 7, 0.11)',
            }),
        );
    }

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

function drawColumnChart(svg, legend, labels, dates, datasets, thresholds = { critico: 10, bajo: 25 }) {
    if (!svg || !legend) return;

    labels = Array.isArray(labels) ? labels : [];
    dates = Array.isArray(dates) ? dates : [];
    datasets = Array.isArray(datasets) ? datasets : [];
    thresholds = {
        critico: Number(thresholds?.critico ?? 10),
        bajo: Number(thresholds?.bajo ?? 25),
    };

    const width = Math.max(900, labels.length * 42);
    const height = 320;
    const margin = { top: 18, right: 20, bottom: 48, left: 62 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;
    const y = (value) => margin.top + ((100 - value) / 100) * plotHeight;
    const groupWidth = labels.length > 0 ? plotWidth / labels.length : plotWidth;
    const slotWidth = datasets.length > 0 ? groupWidth / datasets.length : groupWidth;
    const barWidth = Math.max(2, Math.min(18, slotWidth * 0.72));
    const numberFormat = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 });

    svg.replaceChildren();
    legend.replaceChildren();
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.setAttribute('preserveAspectRatio', 'xMinYMid meet');
    svg.style.width = `${width}px`;
    svg.style.height = `${height}px`;

    const values = datasets.flatMap((dataset) => dataset.values.filter(Number.isFinite));
    if (values.length === 0) {
        const empty = createSvgElement('text', {
            x: width / 2,
            y: height / 2,
            'text-anchor': 'middle',
            fill: 'var(--bs-secondary-color)',
        });
        empty.textContent = 'No hay niveles registrados';
        svg.append(empty);
        return;
    }

    svg.append(
        createSvgElement('rect', {
            x: margin.left,
            y: y(thresholds.critico),
            width: plotWidth,
            height: y(0) - y(thresholds.critico),
            fill: 'rgba(220, 53, 69, 0.08)',
        }),
        createSvgElement('rect', {
            x: margin.left,
            y: y(thresholds.bajo),
            width: plotWidth,
            height: y(thresholds.critico) - y(thresholds.bajo),
            fill: 'rgba(255, 193, 7, 0.11)',
        }),
    );

    for (let tick = 0; tick <= 4; tick += 1) {
        const value = tick * 25;
        const tickY = y(value);
        const line = createSvgElement('line', {
            x1: margin.left,
            x2: width - margin.right,
            y1: tickY,
            y2: tickY,
            stroke: 'var(--bs-border-color)',
        });
        const label = createSvgElement('text', {
            x: margin.left - 9,
            y: tickY + 4,
            'text-anchor': 'end',
            fill: 'var(--bs-secondary-color)',
            'font-size': 12,
        });
        label.textContent = `${value}%`;
        svg.append(line, label);
    }

    const labelStep = Math.max(1, Math.ceil(labels.length / 8));
    labels.forEach((label, index) => {
        if (index % labelStep !== 0 && index !== labels.length - 1) return;
        const text = createSvgElement('text', {
            x: margin.left + groupWidth * (index + 0.5),
            y: height - 12,
            'text-anchor': 'middle',
            fill: 'var(--bs-secondary-color)',
            'font-size': 12,
        });
        text.textContent = label;
        svg.append(text);
    });

    datasets.forEach((dataset, datasetIndex) => {
        const color = palette[dataset.color] || palette.no_aplica;
        dataset.values.forEach((value, index) => {
            if (!Number.isFinite(value)) return;
            const x = margin.left + groupWidth * index + slotWidth * (datasetIndex + 0.5) - barWidth / 2;
            const bar = createSvgElement('rect', {
                x,
                y: y(value),
                width: barWidth,
                height: Math.max(0, y(0) - y(value)),
                rx: Math.min(3, barWidth / 3),
                fill: color,
                opacity: 0.9,
            });
            const title = createSvgElement('title');
            title.textContent = `${dates[index]} · ${dataset.label}: ${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(value)}%`;
            bar.append(title);
            svg.append(bar);
        });

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

function renderLevelStats(container, datasets, dates, thresholds = { critico: 10, bajo: 25 }) {
    if (!container) return;
    container.replaceChildren();

    const table = document.createElement('table');
    table.className = 'table table-sm align-middle mb-2 lecturas-stats-table';
    const head = document.createElement('thead');
    const headerRow = document.createElement('tr');
    for (const title of ['Consumible', 'Último nivel', 'Promedio', 'Mínimo', 'Máximo', 'Cambio', 'Estado']) {
        const cell = document.createElement('th');
        cell.scope = 'col';
        cell.textContent = title;
        headerRow.append(cell);
    }
    head.append(headerRow);
    const body = document.createElement('tbody');
    const numberFormat = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 });
    const format = (value) => `${numberFormat.format(value)}%`;

    for (const dataset of datasets) {
        const validValues = dataset.values.filter(Number.isFinite);
        if (validValues.length === 0) continue;

        let latestIndex = -1;
        for (let index = dataset.values.length - 1; index >= 0; index -= 1) {
            if (Number.isFinite(dataset.values[index])) {
                latestIndex = index;
                break;
            }
        }
        let previousValue = null;
        for (let index = latestIndex - 1; index >= 0; index -= 1) {
            if (Number.isFinite(dataset.values[index])) {
                previousValue = dataset.values[index];
                break;
            }
        }

        const latest = dataset.values[latestIndex];
        const average = validValues.reduce((sum, value) => sum + value, 0) / validValues.length;
        const minimum = Math.min(...validValues);
        const maximum = Math.max(...validValues);
        const change = previousValue === null ? null : latest - previousValue;
        const status = latest <= thresholds.critico
            ? ['Crítico', 'danger']
            : latest <= thresholds.bajo
                ? ['Bajo', 'warning']
                : ['Normal', 'success'];
        const row = document.createElement('tr');
        const name = document.createElement('th');
        name.scope = 'row';
        name.textContent = dataset.label;
        row.append(name);

        for (const value of [latest, average, minimum, maximum]) {
            const cell = document.createElement('td');
            cell.textContent = format(value);
            row.append(cell);
        }

        const changeCell = document.createElement('td');
        if (change === null) {
            changeCell.textContent = '—';
        } else {
            changeCell.textContent = `${change > 0 ? '+' : ''}${numberFormat.format(change)} pp`;
            changeCell.className = change < 0 ? 'text-danger' : change > 0 ? 'text-success' : 'text-body-secondary';
            changeCell.title = `Comparado con la lectura anterior a ${dates[latestIndex] || ''}`;
        }
        row.append(changeCell);

        const statusCell = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = `badge text-bg-${status[1]}`;
        badge.textContent = status[0];
        statusCell.append(badge);
        row.append(statusCell);
        body.append(row);
    }

    if (body.childElementCount === 0) {
        const message = document.createElement('p');
        message.className = 'small text-body-secondary mb-2';
        message.textContent = 'No hay porcentajes válidos para resumir.';
        container.append(message);
        return;
    }

    table.append(head, body);
    container.append(table);
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
    const chartLevels = document.querySelector('[data-lecturas-chart="niveles"]');
    const levelStats = document.querySelector('[data-levels-stats]');
    const levels = Array.isArray(data.niveles)
        ? data.niveles.map((dataset) => ({
            ...dataset,
            values: Array.isArray(dataset.values)
                ? dataset.values
                : Array.isArray(dataset.valores)
                    ? dataset.valores
                    : [],
        }))
        : [];
    const thresholds = data.umbrales || { critico: 10, bajo: 25 };

    try {
        drawColumnChart(
            chartLevels,
            document.querySelector('[data-chart-legend="niveles"]'),
            data.etiquetas,
            data.fechas,
            levels,
            thresholds,
        );
        renderLevelStats(levelStats, levels, data.fechas || [], thresholds);
    } catch (error) {
        console.error('No se pudo dibujar la gráfica de niveles:', error);
        if (chartLevels) {
            chartLevels.replaceChildren();
            const message = createSvgElement('text', {
                x: 450,
                y: 160,
                'text-anchor': 'middle',
                fill: 'var(--bs-secondary-color)',
            });
            message.textContent = 'No se pudo mostrar la gráfica de niveles';
            chartLevels.append(message);
        }
        if (levelStats) {
            levelStats.textContent = 'No se pudieron calcular las estadísticas de niveles.';
        }
    }
}

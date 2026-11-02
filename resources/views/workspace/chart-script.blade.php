@once
@push('workspace-scripts')
<script src="{{ asset('assets/js/Chart.min.js') }}"></script>
<script>
// Shared chart helper for the administrative panels (Chart.js 2.8). Colours follow the stat tiles.
window.ptmChart = function (id, type, labels, datasets, options) {
    var canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return null;
    var palette = ['#176ce0', '#16a36a', '#7b4fd6', '#f08c1a', '#d63a83', '#0e9aa7'];
    var stacked = !!(options && options.stacked);
    datasets.forEach(function (set, i) {
        var colour = set.colour || palette[i % palette.length];
        set.borderColor = colour;
        set.backgroundColor = type === 'line' ? colour + '22' : colour;
        set.pointRadius = type === 'line' ? 2 : undefined;
        set.borderWidth = type === 'line' ? 2 : 0;
        set.lineTension = 0.3;
        set.fill = type === 'line' && i === 0;
    });
    return new Chart(canvas.getContext('2d'), {
        type: type,
        data: {labels: labels, datasets: datasets},
        options: {
            responsive: true, maintainAspectRatio: false, animation: {duration: 300},
            legend: {position: 'bottom', labels: {boxWidth: 10, fontSize: 11}},
            tooltips: {mode: 'index', intersect: false},
            scales: type === 'doughnut' ? {} : {
                xAxes: [{stacked: stacked, gridLines: {display: false}, ticks: {fontSize: 10, maxRotation: 0, autoSkip: true}}],
                yAxes: [{stacked: stacked, ticks: {beginAtZero: true, precision: 0, fontSize: 10}, gridLines: {color: '#eef2f8'}}]
            }
        }
    });
};
</script>
@endpush
@endonce

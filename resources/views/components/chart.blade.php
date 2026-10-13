@props(['config'])
<canvas data-chart="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) }}" {{ $attributes->merge(['role' => 'img', 'aria-label' => $attributes->get('aria-label', 'Gráfico')]) }}></canvas>

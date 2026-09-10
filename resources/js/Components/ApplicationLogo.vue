<!--
    coreAgent's brand mark — a five-petal pinwheel "spark", extracted from the
    COREWORD reference design (coreword-logo-mark_bb79cc1e.png). Four petals
    inherit `currentColor` (var(--cw-blue) via .brand-mark); one is fixed
    orange as the reference's own accent petal.

    Each petal is a kite: center (100,100), near point at r=15, tip at r=92,
    shoulders at r=46 ± 15°, one petal per 72° step starting at -90°
    (bottom) — regenerate with point(r,deg) = (100 + r·cos(deg), 100 - r·sin(deg))
    if the mark ever needs to change size or petal count.
-->
<template>
    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
        <polygon fill="currentColor" points="100,115 88.09,144.43 100,192 111.91,144.43" />
        <polygon fill="currentColor" points="114.27,104.64 138.58,125.05 187.5,128.43 145.94,102.41" />
        <polygon fill="var(--cw-orange, #f28c28)" points="108.82,87.86 135.75,71.05 154.08,25.57 116.48,57.06" />
        <polygon fill="currentColor" points="91.18,87.86 83.52,57.06 45.92,25.57 64.25,71.05" />
        <polygon fill="currentColor" points="85.73,104.64 54.06,102.41 12.5,128.43 61.42,125.05" />
    </svg>
</template>

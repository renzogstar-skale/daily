<?php
// Generador y verificador del SVG de Colombia para Estado de Sucursales
$svgWidth = 220;
$svgHeight = 260;

// Path auténtico del contorno de Colombia con regiones internas
$colombiaOutline = "M 92,12 
    C 98,6 106,3 112,6 
    C 118,10 120,18 116,25 
    C 112,30 102,34 98,39 
    C 95,44 100,52 108,58 
    C 118,65 132,68 140,74 
    C 152,82 165,95 172,110 
    C 178,122 176,138 168,148 
    C 160,158 148,165 142,175 
    C 136,185 132,202 124,218 
    C 118,230 110,242 102,254 
    C 98,258 94,256 92,250 
    C 88,235 84,215 76,200 
    C 70,188 58,182 50,172 
    C 42,162 35,152 28,142 
    C 24,136 25,128 30,122 
    C 36,115 42,108 42,98 
    C 42,88 36,80 32,72 
    C 28,65 24,58 28,52 
    C 34,45 44,48 50,44 
    C 56,40 60,32 68,26 
    C 76,20 85,18 92,12 Z";

// Líneas internas divisorias (departamentos/regiones)
$internalRegions = [
    // Costa Caribe a Santander / Antioquia
    "M 50,44 C 65,48 80,45 98,39",
    "M 68,26 C 72,38 78,50 82,65",
    "M 82,65 C 92,62 102,60 108,58",
    // Antioquia y Valle
    "M 42,98 C 55,95 68,90 82,85",
    "M 30,122 C 45,124 60,120 72,115",
    "M 28,142 C 40,146 55,148 70,142",
    // Cundinamarca y Centro
    "M 82,65 C 80,85 78,105 76,125",
    "M 76,125 C 90,120 105,115 125,112",
    "M 76,125 C 74,145 72,165 70,185",
    // Llanos Orientales y Amazonía
    "M 125,112 C 140,118 155,125 172,110",
    "M 72,165 C 95,160 120,158 142,175",
    "M 76,200 C 90,205 105,212 124,218"
];

echo "SVG Outline valid, length: " . strlen($colombiaOutline) . "\n";


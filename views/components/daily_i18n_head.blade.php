{{-- Arranque del idioma global ES/EN para vistas sin el layout principal (móvil, documentos). --}}
@php $dailyLang = \Grocy\Helpers\DailyI18n::Lang(); @endphp
<script>window.DAILY_I18N_SRC = '{{ $U('/i18n/daily_en.js?v=', true) }}{{ daily_asset_version('/i18n/daily_en.js') }}';</script>
@if($dailyLang === 'en')
<script src="{{ $U('/i18n/daily_en.js?v=', true) }}{{ daily_asset_version('/i18n/daily_en.js') }}"></script>
@endif
<script src="{{ $U('/js/daily_i18n.js?v=', true) }}{{ daily_asset_version('/js/daily_i18n.js') }}"></script>

<dl class="surface pbr-plan-summary">
    @foreach(\App\Services\Portal\BusinessPlanContent::FIELDS as $field=>$label)
    @if(filled($values[$field] ?? null))
    <div><dt>{{ $label }}</dt><dd>{{ $field === 'business_stage' ? (\App\Services\Portal\BusinessPlanContent::STAGES[$values[$field]] ?? $values[$field]) : $values[$field] }}</dd></div>
    @endif
    @endforeach
</dl>

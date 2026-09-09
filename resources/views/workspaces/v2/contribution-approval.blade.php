@extends('layouts.student-portal')
@section('title', 'Contribution Item Approval')
@section('content')
<main style="max-width:900px;margin:32px auto;padding:24px">
    <a href="{{ route('workspaces.tools.v2.contribution.show', $workspace) }}">Contribution Register သို့</a>
    <h1>Contribution Item Approval</h1>
    <p>{{ $workspace->name }} · {{ $setup['currency_code'] }} · {{ $subject['status'] }}</p>
    <p>ဤ approval သည် ဖော်ပြထားသော contribution item တစ်ခုအတွက်သာ ဖြစ်ပါသည်။</p>
    @if($errors->any())
        <div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <h2>{{ $subject['description'] ?? 'Contribution' }}</h2>
    <p>Partner: {{ $subject['partner_snapshot']['display_name'] ?? $subject['partner_key'] }}</p>
    <p>Valuation Date: {{ $setup['valuation_date'] }} · Valuation Owner: {{ $setup['valuation_owner_reference'] }}</p>
    <p>Contribution Period: {{ $setup['contribution_period']['start_date'] }} — {{ $setup['contribution_period']['end_date'] }}</p>
    <dl>
        @foreach($subject as $field=>$value)
            @continue(in_array($field, ['id','partner_snapshot','partner_key'], true))
            <dt><strong>{{ ucwords(str_replace('_',' ',$field)) }}</strong></dt>
            <dd>
                @if(is_array($value))
                    @foreach($value as $reference)
                        @if(is_scalar($reference))<div>{{ $reference }}</div>@endif
                    @endforeach
                @elseif(is_bool($value))
                    {{ $value ? 'Yes' : 'No' }}
                @else
                    {{ $value ?? '—' }}
                @endif
            </dd>
        @endforeach
    </dl>
    <h3>Designated Approvers</h3>
    <ul>@foreach($approverNames as $name)<li>{{ $name }}</li>@endforeach</ul>
    <h3>Current Approval Records</h3>
    <ul>
        @foreach($records as $record)
            <li>{{ $approverNames[$record['user_id']] ?? $record['user_id'] }} · {{ $record['approved_at'] }} · {{ $record['evidence_reference'] }}</li>
        @endforeach
    </ul>
    @php
        $alreadyApproved = in_array((int) auth()->id(), array_column($records, 'user_id'), true);
        $operation = $subject['status'] === 'reviewed' ? 'agree'
            : ($subject['status'] === 'delivered' ? 'accept' : null);
    @endphp
    @if($operation !== null && !$alreadyApproved)
        <form method="POST" action="{{ route('workspaces.tools.v2.contribution.'.$operation, ['workspace'=>$workspace,'artifactId'=>$artifactId,'itemId'=>$subject['id']]) }}">
            @csrf
            <input type="hidden" name="edit_version" value="{{ $version }}">
            @if($operation === 'agree')
                <p>ဖော်ပြထားသော item နှင့် agreed value ကို အတည်ပြုပါမည်။</p>
                <label>Agreed Value
                    <input type="text" name="agreed_value" value="{{ old('agreed_value', $subject['agreed_value'] ?? $subject['reviewed_value'] ?? '') }}" required>
                </label>
            @else
                <p>ဖော်ပြထားသော delivery နှင့် accepted value ကို approval အသစ်ဖြင့် အတည်ပြုပါမည်။</p>
            @endif
            <p><label>Evidence Reference<input name="evidence_reference" value="{{ old('evidence_reference') }}" required></label></p>
            <p><label>Notes<textarea name="notes">{{ old('notes') }}</textarea></label></p>
            <button type="submit">{{ $operation === 'agree' ? 'Approve Agreement' : 'Approve Acceptance' }}</button>
        </form>
    @else
        <p>သင့်အတွက် လက်ရှိ approval action မရှိပါ။</p>
    @endif
</main>
@endsection

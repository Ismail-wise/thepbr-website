@extends('portal.layout')
@section('title', 'Knowledge ထည့်မယ် · '.$workspace->name)
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><h1>Knowledge ထည့်မယ်</h1><p>{{ $workspace->name }} · Owner သီးသန့်</p></div></div>
    <div class="pbr-knowledge-create">
    <form class="surface pbr-knowledge-form" method="post" enctype="multipart/form-data" action="{{ route('portal.workspaces.knowledge.store', $workspace) }}" data-knowledge-submit>
        @csrf
        <label for="knowledge-title">ခေါင်းစဉ်</label><input id="knowledge-title" name="title" maxlength="160" value="{{ old('title') }}" required autofocus>
        <label for="knowledge-business">အခြေအနေ</label><select id="knowledge-business" name="business_status" required><option value="">ရွေးပေးပါ</option>@foreach(\App\Models\WorkspaceKnowledgeSource::BUSINESS_STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('business_status')===$value)>{{ $label }}</option>@endforeach</select>
        <label for="knowledge-content">စာသား</label><textarea id="knowledge-content" name="content" rows="7" maxlength="60000">{{ old('content') }}</textarea>
        <div class="pbr-knowledge-or"><span>သို့မဟုတ်</span></div>
        <label for="knowledge-file">Photo / File</label><input id="knowledge-file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,application/pdf,image/jpeg,image/png,image/webp,text/plain" aria-describedby="knowledge-file-help">
        <span id="knowledge-file-help" class="small muted">PDF · JPG · PNG · WebP · TXT / {{ number_format(config('pbr_knowledge.upload_kb')/1024, 0) }} MB max · စာသား သို့မဟုတ် ဖိုင်တစ်ခု</span>
        <details class="quiet-details"><summary>Privacy &amp; AI</summary><p>သင်စစ်ပြီး အတည်ပြုတဲ့ စာသားကိုပဲ ဒီ Workspace ရဲ့ AI က သုံးပါတယ်။ Portal အတွင်း Owner သီးသန့်ပါ။ စာသားဖတ်ယူခြင်းနဲ့ အဖြေပေးခြင်းအတွက် ချိတ်ဆက်ထားသော AI provider က အကြောင်းအရာကို စီမံပေးပါတယ်။</p></details>
        <div class="page-actions"><button class="btn" data-knowledge-button>သိမ်းပြီး စစ်မယ်</button><a class="btn secondary" href="{{ route('portal.workspaces.knowledge.index', $workspace) }}">နောက်သို့</a></div><p class="small muted" data-knowledge-progress role="status"></p>
    </form>
    </div>
</section>
@endsection

@if(count($links ?? []) > 0)
<details class="pbr-ai-sources"><summary>အဖြေအတွက် ရွေးပေးထားသော Knowledge · {{ count($links) }}</summary><ul>@foreach($links as $link)<li><span>[{{ $link['reference'] }}]</span> @if($link['url'])<a href="{{ $link['url'] }}">{{ $link['title'] }}</a>@else<span>{{ $link['title'] }} · ဖယ်ထားပြီး</span>@endif <small>{{ \App\Models\WorkspaceKnowledgeSource::BUSINESS_STATUSES[$link['business_status']] ?? '' }}</small></li>@endforeach</ul></details>
@endif

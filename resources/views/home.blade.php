@extends('layouts.site')

@section('title', 'thePBR — မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ သင်တန်း')
@section('description', 'မိတ်ဖက်လုပ်ငန်း စတင်ခြင်း၊ ပြန်လည်ဖွဲ့စည်းခြင်းအတွက် SME လုပ်ငန်းရှင်များအား သင်ကြားပေးသည့် သင်တန်း။')

@section('content')

{{-- ===================== HERO ===================== --}}
<div class="hero on-dark">
  <div class="wrap hero-grid">
    <div>
      <div class="eyebrow">Partnership Business Rules</div>
      <h1>မိတ်ဖက်လုပ်ငန်း တစ်ခုကို စတင်ခြင်း၊ ပြန်လည်ဖွဲ့စည်းခြင်းအတွက် သိထားသင့်သည့် စည်းမျဉ်းများ</h1>
      <p class="lede">မြန်မာနှင့် ထိုင်းနိုင်ငံရှိ SME လုပ်ငန်းရှင်များအတွက် အထူးရေးဆွဲထားသော သင်တန်း။ သီအိုရီထက် လက်တွေ့ကျသော ဥပမာများ၊ ချက်ချင်း သုံးနိုင်သည့် စာချုပ်ပုံစံများနှင့်အတူ။</p>
      <div class="acts">
        <a href="#" class="btn light">Sign Up</a>
        <a href="#" class="btn outline-light">About the Class</a>
      </div>
    </div>

    <div class="deed">
      <div class="kicker">Partnership Agreement</div>
      <h3>အပိုဒ် ၄ — အမြတ်နှင့် အရှုံး ခွဲဝေခြင်း</h3>
      <div class="clause">မိတ်ဖက်တစ်ဦးစီ၏ ထည့်ဝင်မှုသည် ငွေကြေး၊ လုပ်အား သို့မဟုတ် ပစ္စည်းပုံစံ ကွဲပြားနိုင်သဖြင့် ခွဲဝေမှုအချိုးကို စာဖြင့် ကြိုတင် သတ်မှတ်ထားရမည်။</div>
      <div class="sigs">
        <div class="sig"><div class="line"></div><span>မိတ်ဖက် (၁)</span></div>
        <div class="divider"></div>
        <div class="sig b"><div class="line"></div><span>မိတ်ဖက် (၂)</span></div>
      </div>
    </div>
  </div>
</div>

{{-- ===================== PROBLEMS ===================== --}}
<section class="sec-sage">
  <div class="wrap">
    <div class="head-row">
      <div>
        <div class="eyebrow">Common problems</div>
        <h2>မိတ်ဖက်လုပ်ငန်းများ ပြိုကွဲရသည့် အဖြစ်များဆုံး အကြောင်းရင်းများ</h2>
      </div>
      <p>အောက်ပါ မေးခွန်းများကို လုပ်ငန်းမစတင်မီ မဖြေဆိုထားပါက နောင်တွင် အငြင်းပွားမှု ဖြစ်လေ့ရှိပါသည်။</p>
    </div>

    <div class="probs">
      <div class="prob">
        <div class="q"><em>၀၁</em>အမြတ်ဝေစုကို ဘယ်လို ခွဲမလဲ</div>
        <p>တစ်ဦးက ရင်းနှီးငွေ ထည့်ပြီး တစ်ဦးက လုပ်အား ထည့်သည့်အခါ မျှတသော အချိုးကို ဘယ်လို တွက်ချက်မလဲ။</p>
      </div>
      <div class="prob">
        <div class="q"><em>၀၂</em>မိတ်ဖက်တစ်ဦး ထွက်သွားလျှင်</div>
        <p>ရှယ်ယာကို ဘယ်သူက ပြန်ဝယ်မလဲ။ လုပ်ငန်း၏ တန်ဖိုးကို ဘယ်နည်းလမ်းဖြင့် သတ်မှတ်မလဲ။</p>
      </div>
      <div class="prob">
        <div class="q"><em>၀၃</em>ဆုံးဖြတ်ချက် ဘယ်သူ ချမလဲ</div>
        <p>နေ့စဉ်လုပ်ငန်းဆိုင်ရာနှင့် ရင်းနှီးမြှုပ်နှံမှုကဲ့သို့ အကြီးစား ဆုံးဖြတ်ချက်များအတွက် အာဏာကို ဘယ်လို ခွဲခြားထားမလဲ။</p>
      </div>
      <div class="prob">
        <div class="q"><em>၀၄</em>သဘောထား ကွဲလွဲလျှင်</div>
        <p>စာချုပ်ထဲတွင် ကြိုတင် ထည့်သွင်းမထားသဖြင့် လုပ်ငန်းရှင်များ အများဆုံး နောင်တရရသည့် အချက်များ။</p>
      </div>
    </div>
  </div>
</section>

{{-- ===================== INSTRUCTOR ===================== --}}
<section class="instructor-section" aria-labelledby="instructor-name">
  <div class="wrap tutor">
    <div class="tutor-visual">
      <figure class="portrait">
        <img
          src="{{ asset('images/instructors/nyan-lin-aung.webp') }}"
          alt="ဆရာ ဥာဏ်လင်းအောင် — PBR Instructor & Business Consultant"
          width="960"
          height="1200"
          loading="lazy"
          decoding="async"
        >
        <figcaption class="portrait-caption">
          <span>PBR Instructor</span>
          <strong>Myanmar · Thailand</strong>
        </figcaption>
      </figure>

      <div class="tutor-highlights" aria-label="Instructor milestones">
        <article>
          <strong>2019</strong>
          <span>Partnership Turning Point</span>
        </article>
        <article>
          <strong>2025</strong>
          <span>PBR စတင်သင်ကြား</span>
        </article>
        <article>
          <strong>Batch 4</strong>
          <span>သင်ကြားပြီးသောအဆင့်</span>
        </article>
        <article>
          <strong>MM + TH</strong>
          <span>လက်တွေ့လုပ်ငန်းများ</span>
        </article>
      </div>
    </div>

    <div class="tutor-content">
      <div class="eyebrow">About the instructor</div>
      <h2 id="instructor-name">ဆရာ ဥာဏ်လင်းအောင်</h2>
      <div class="role">PBR Instructor &amp; Business Consultant</div>

      <div class="tutor-story">
        <p>ဆရာ ဥာဏ်လင်းအောင်သည် လူငယ်ဘဝကတည်းက စပ်တူလုပ်ငန်းများကို ကိုယ်တိုင် လက်တွေ့လုပ်ကိုင်ခဲ့ပြီး အောင်မြင်မှုများသာမက မအောင်မြင်မှုများနှင့် ငွေကြေးဆုံးရှုံးမှုများကိုပါ ကိုယ်တိုင်ကြုံတွေ့ခဲ့သူ ဖြစ်ပါသည်။ ၂၀၁၉ ခုနှစ်တွင် အရွယ်အစားကြီးမားသည့် စပ်တူလုပ်ငန်းတစ်ခုမှ ငွေကြေးဆုံးရှုံးမှုကြီးမားစွာ ကြုံတွေ့ခဲ့ရခြင်းသည် စပ်တူလုပ်ငန်းအပေါ် အမြင်ကို ပြောင်းလဲစေခဲ့သည့် အရေးကြီးသော Turning Point ဖြစ်ခဲ့ပါသည်။</p>

        <p>ထိုအချိန်မှစ၍ Partner ရွေးချယ်မှု၊ Ownership Structure၊ Role &amp; Responsibility၊ Profit Sharing၊ Decision Making၊ Conflict Management နှင့် Exit Plan များကို စနစ်တကျလေ့လာပြီး ကိုယ်တိုင်၏ လုပ်ငန်းများတွင် ပြန်လည်အသုံးချခဲ့ပါသည်။ ယနေ့တွင် မြန်မာနိုင်ငံနှင့် ထိုင်းနိုင်ငံ၊ ချင်းမိုင်မြို့တို့တွင် စပ်တူလုပ်ငန်းများကို လက်တွေ့လုပ်ကိုင်လျက်ရှိပါသည်။</p>

        <p>၂၀၂၅ ခုနှစ်မှစတင်၍ Partnership Business Rules (PBR) ကို သင်ကြားခဲ့ပြီး ယခု Batch 4 အထိ ရောက်ရှိလာပြီဖြစ်ပါသည်။ သင်တန်းများနှင့် Business Consultation Sessions များမှတစ်ဆင့် လုပ်ငန်းရှင်များအား အမှားများကို ကြိုတင်မြင်နိုင်ရန်၊ ရှိပြီးသား Partnership များကို ပိုမိုစနစ်တကျ တည်ဆောက်နိုင်ရန်နှင့် Partner ကြောင့် ဖြစ်လာနိုင်သော ထိခိုက်မှုများကို လျှော့ချနိုင်ရန် ကူညီပေးလျက်ရှိပါသည်။</p>
      </div>

      <div class="tutor-expertise" aria-label="Areas of expertise">
        <span>Partner Selection</span>
        <span>Ownership Structure</span>
        <span>Roles &amp; Responsibilities</span>
        <span>Profit Sharing</span>
        <span>Decision Making</span>
        <span>Conflict Management</span>
        <span>Exit Planning</span>
      </div>

      <div class="tutor-mission">
        <strong>ဆရာဥာဏ်၏ ရည်ရွယ်ချက်</strong>
        <p>မြန်မာလုပ်ငန်းရှင်များ စပ်တူလုပ်ငန်းကို မှန်ကန်စွာ တည်ဆောက်နိုင်စေရန်၊ Myanmar SMEs များ ပိုမိုခိုင်မာလာစေရန်နှင့် ပိုမိုကောင်းမွန်သော Myanmar Business Culture တစ်ခုကို ဖန်တီးပေးရန် ဖြစ်ပါသည်။</p>
      </div>

      <blockquote class="tutor-quote">
        “ယုံကြည်မှုရှိလို့ Rules မလိုတာ မဟုတ်ပါဘူး။ ယုံကြည်မှုကို ရေရှည်ထိန်းထားချင်လို့ Rules လိုတာပါ။”
      </blockquote>
    </div>
  </div>
</section>

{{-- ===================== REVIEWS ===================== --}}
<section class="sec-sage">
  <div class="wrap">
    <div class="head-row">
      <div>
        <div class="eyebrow">From past students</div>
        <h2>သင်တန်းသားများ၏ အမြင်</h2>
      </div>
    </div>
    <div class="revs">
      <div class="rev">
        <div class="quote">“ကျွန်တော်တို့ ညီအစ်ကိုနှစ်ယောက် လုပ်နေတဲ့ လုပ်ငန်းကို စာချုပ်နဲ့ ပြန်ဖွဲ့ဖို့ ဒီသင်တန်းက အထောက်အကူ အများကြီး ဖြစ်ခဲ့ပါတယ်။”</div>
        <div class="who"><b>ကိုမင်းသန့်</b><span>Construction supply · ရန်ကုန်</span></div>
      </div>
      <div class="rev">
        <div class="quote">“အမြတ်ခွဲဝေမှုကို ပါးစပ်နဲ့ပဲ ပြောထားခဲ့တာ ဘယ်လောက် အန္တရာယ်များလဲဆိုတာ သင်တန်းတက်မှ သိလိုက်ရပါတယ်။”</div>
        <div class="who"><b>ဒေါ်ခင်မာဝင်း</b><span>Trading · မန္တလေး</span></div>
      </div>
      <div class="rev">
        <div class="quote">“ဥပဒေစကားလုံးတွေကို လုပ်ငန်းရှင်နားလည်အောင် ရှင်းပြပေးတာ အကောင်းဆုံးပါပဲ။ လက်တွေ့ သုံးလို့ရပါတယ်။”</div>
        <div class="who"><b>ကိုအောင်ကျော်</b><span>Restaurant group · ချင်းမိုင်</span></div>
      </div>
    </div>
  </div>
</section>

{{-- ===================== LATEST ARTICLES (from the database) ===================== --}}
@if($articles->isNotEmpty())
<section>
  <div class="wrap">
    <div class="head-row">
      <div>
        <div class="eyebrow">Latest articles</div>
        <h2>နောက်ဆုံးရ ဆောင်းပါးများ</h2>
      </div>
      <a href="{{ route('articles.index') }}" class="btn ghost">View all articles</a>
    </div>

    <div class="grid">
      @foreach($articles as $article)
        <a href="{{ route('articles.show', $article->slug) }}" class="card">
          <div class="thumb">
            @if($article->cover_image)
              <img src="{{ asset('storage/' . $article->cover_image) }}" alt="">
            @endif
          </div>
          <div class="body">
            <div class="tag">{{ $article->category }}</div>
            <h3>{{ $article->title }}</h3>
            <div class="meta">{{ $article->burmese_date }}</div>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- ===================== CTA ===================== --}}
<div class="cta on-dark">
  <div class="wrap">
    <div class="eyebrow" style="justify-content:center">Get started</div>
    <h2>အကောင့်ဖွင့်ပြီး Resources များကို စတင် ရယူလိုက်ပါ</h2>
    <p>အကောင့်ဖွင့်ရန် အခမဲ့ ဖြစ်ပါသည်။ ဆောင်းပါးများနှင့် သင်တန်းအချက်အလက်များကို မည်သူမဆို ဖတ်ရှုနိုင်ပါသည်။</p>
    <div class="codebox">
      <b>Students</b>
      <span>သင်တန်းတက်ရောက်ပြီးသူများသည် အကောင့်ဖွင့်စဉ် ဆရာပေးထားသော Code ကို ထည့်သွင်းပါ။ စစ်ဆေးအတည်ပြုပြီးပါက Student Portal ကို ဝင်ရောက်နိုင်ပါမည်။</span>
    </div>
    <div class="acts">
      <a href="#" class="btn light">Sign Up</a>
      <a href="#" class="btn outline-light">Log In</a>
    </div>
  </div>
</div>

@endsection

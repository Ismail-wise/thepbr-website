# Partner Portal — Live ချိတ်ဆက်မှု စစ်ဆေးရန်

## ပြင်ဆင်ထားသော Code

Repository — `Ismail-wise/thepbr-website`

Branch — `feature/student-partner-portal`

AI Engine — `Ismail-wise/partner-ai` ရဲ့ `feature/pbr-integration-v1` branch။ AI Engine အသစ်တစ်ခု အစားထိုးရေးထားခြင်း မဟုတ်ပါ။ မူလ Knowledge Base နှင့် private service ကို Laravel က ခေါ်ပါမယ်။

ပါဝင်သည်များ — အမှန်တကယ် Password Login နှင့် Signup၊ Admin ဝင်ခွင့်/ရက်အရေအတွက် စီမံခြင်း၊ App Launcher၊ PBR AI Chat/History၊ မူလ PartnerDynamics၊ Capital/Share တွက်ချက်မှု၊ Checklist နှင့် ကိုယ်ပိုင်သိမ်းဆည်းမှု။

## Live ပေါ်မတင်မီ လိုအပ်သော Server အချက်အလက်

`scripts/pbr-portal-preflight.php` ကို လက်ရှိ Server ပေါ်မှာ run ပါ။ ဒီ script က Read-only စစ်ဆေးပြီး API key၊ Password၊ User အမည်စာရင်းနှင့် Database credentials ကို မထုတ်ပြပါ။

ရလဒ်မှာ လက်ရှိ Laravel/Database အခြေခံရှိမရှိ၊ Admin Account ရှိမရှိ၊ AI service နဲ့ Knowledge Base အဆင်သင့်ဖြစ်မဖြစ်ကို မြင်ရပါမယ်။ Git source HEAD နှင့် working-tree clean/dirty အခြေအနေကိုလည်း သီးခြားစစ်ဖို့လိုပါတယ်။ Server source ကို မစစ်ရသေးသဖြင့် ဒီ branch ကို Live ပေါ် အလိုအလျောက် မတင်ရသေးပါ။

## နောက်ဆုံး Release အဆင့်

1. လက်ရှိ Live source နှင့် GitHub branch ကို တိုက်စစ်ပြီး Public Website / PartnerDynamics မပြောင်းကြောင်း စစ်ပါ။
2. သီးခြား Test/Preview မှာ လက်ရှိ Server နှင့် ကိုက်ညီသော dependencies ဖြင့် SQLite integration tests စမ်းပါ။ Production database ကို tests အတွက် မသုံးပါ။
3. Database/Config backup နှင့် သီးခြား Restore drill ပြီးစီးမှ Code/Migration release ပြင်ဆင်ပါ။ လက်ရှိ Student ဝင်ခွင့်နှင့် သက်တမ်းများကို မဖျက်ပါ။
4. အသစ်ထည့်ထားသော migration ကို apply ပြီး `PBR_PARTNER_PORTAL_ENABLED=true` ဖွင့်ပါ။ Partner AI private service မှ `knowledgeReady=true` နှင့် vectors ရှိကြောင်း စစ်ပါ။
5. Live Website ရဲ့ Login/Create Account ကနေ အကောင့်အသစ်ဖွင့်၊ Admin က ရက်အရေအတွက်ပေး၊ User ပြန်ဝင်ပြီး PBR AI ကို အမှန်တကယ် မေးကြည့်ပါ။ Saved Results နဲ့ PartnerDynamics ကိုလည်း မိမိအကောင့်ဖြင့် စမ်းပါ။

ဒီအဆင့်ရောက်မှ Live ချိတ်ဆက်မှုကို အမှန်တကယ် စမ်းနိုင်ပါမယ်။ Local Demo account ရွေးချယ်သည့်စနစ်ကို Live ပေါ် မတင်ပါ။ Gemini API key ကို Chat ထဲ မပို့ပါနှင့်။

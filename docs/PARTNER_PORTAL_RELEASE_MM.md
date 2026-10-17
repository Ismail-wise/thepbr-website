# Partner Portal — Live ချိတ်ဆက်မှု စစ်ဆေးရန်

## ပြင်ဆင်ထားသော Code

Repository — `Ismail-wise/thepbr-website`

Branch — `feature/student-partner-portal`

AI Engine — `Ismail-wise/partner-ai` ရဲ့ `feature/pbr-integration-v1` branch။ AI Engine အသစ်တစ်ခု အစားထိုးရေးထားခြင်း မဟုတ်ပါ။ မူလ Knowledge Base နှင့် private service ကို Laravel က ခေါ်ပါမယ်။

ပါဝင်သည်များ — အမှန်တကယ် Password Login နှင့် Signup၊ Admin ဝင်ခွင့်/ရက်အရေအတွက် စီမံခြင်း၊ App Launcher၊ PBR AI Chat/History၊ မူလ PartnerDynamics၊ Capital/Share တွက်ချက်မှု၊ Checklist နှင့် ကိုယ်ပိုင်သိမ်းဆည်းမှု။

## Live ပေါ်မတင်မီ လိုအပ်သော Server အချက်အလက်

`scripts/pbr-portal-preflight.php` ကို လက်ရှိ Server ပေါ်မှာ run ပါ။ ဒီ script က Read-only စစ်ဆေးပြီး API key၊ Password၊ User အမည်စာရင်းနှင့် Database credentials ကို မထုတ်ပြပါ။

ရလဒ်မှာ လက်ရှိ Laravel/Database အခြေခံရှိမရှိ၊ Admin Account ရှိမရှိ၊ AI service နဲ့ Knowledge Base အဆင်သင့်ဖြစ်မဖြစ်ကို မြင်ရပါမယ်။ Git source HEAD နှင့် working-tree clean/dirty အခြေအနေကိုလည်း စစ်ပါသည်။ ဒီ branch ကို Live ပေါ် အလိုအလျောက် မတင်ပါ။

### 2026-10-17 စစ်ဆေးပြီး အခြေအနေ

User ပြန်ပို့ထားသော preflight output အရ Live source သည် clean ဖြစ်ပြီး `b71122dee5f0585807223b94b33211e1399df4dd` နှင့် ကိုက်ညီပါသည်။ PHP 8.4.24၊ MySQL connection၊ မူလ Student / PartnerDynamics / AI tables နှင့် active Admin ရှိပါသည်။ Portal tables အသစ် လေးခု မရှိသေးပါ။ Portal flag ပိတ်ထားပါသည်။

မူလ `partner-ai` source သည် `cd16ba3b543fdbc66863f1f71128f4e7f02bb18c` ဖြစ်ပြီး local service / shared secret / knowledgeReady / vectors 657 စစ်ဆေးချက် အောင်မြင်ပါသည်။ ဤအချက်များသည် user ပေးပို့သော Server output အပေါ် အခြေခံပါသည်။ Empty-message handshake သည် အမှန်တကယ် AI အဖြေ ထုတ်ခြင်း မဟုတ်ပါ။

### Backup နှင့် အမှန်တကယ် Restore စမ်းသပ်ခြင်း

`sudo bash scripts/pbr-portal-backup.sh /var/www/thepbr-laravel` ကို Server ပေါ်မှာ run ပါ။ Remote မှ ရယူသည့်အခါ စစ်ဆေးပြီးသော commit သီးသန့်ကိုသာ သုံးပါ။

- Live source နှင့် production/local database configuration ကို ပြန်စစ်ပါသည်။ Local MySQL root access မရပါက `LOCAL_DATABASE_ADMIN_UNAVAILABLE` ဖြင့် ရပ်ပါမည်။ Password ကို Chat ထဲ ပို့ရန် မလိုပါ။
- Root သာ ဝင်နိုင်သော `/root/thepbr-deploy-backups` အောက်မှာ database၊ application/config နှင့် local upload files ကို သိမ်းပါသည်။ ပြန်တည်ဆောက်နိုင်သော vendor/node_modules နှင့် ယာယီ cache/session/logs ကို မထည့်ပါ။ ပြင်ပ storage/symlink များကို backup လုပ်ပြီးဟု မယူဆပါ။
- Database အသစ်နှင့် ယာယီ MySQL account သီးသန့် ဖန်တီးပြီး live database ကို ထို account မှ ဝင်မရကြောင်း စစ်ပါသည်။ ထို့နောက် backup ကို restore လုပ်၊ ပြန် dump လုပ်ပြီး schema/data နှိုင်းယှဉ်ပါသည်။ Custom view/trigger/routine/event သို့မဟုတ် InnoDB မဟုတ်သော table ရှိပါက သီးခြား review လုပ်ရန် ရပ်ပါသည်။
- Application archive ကိုလည်း private directory ထဲ ပြန်ဖွင့်ပြီး contents/config/local files ကို စစ်ပါသည်။ ပြီးလျှင် ယာယီ database/account/restore files ကို ရှင်းပြီး verified marker ထုတ်ပါသည်။ Backup မူရင်းကို မဖျက်ပါ။
- Code/migration/access မပြောင်းပါ၊ Portal ကို မဖွင့်ပါ။ အောင်မြင်ပါက `PBR_PORTAL_BACKUP=COMPLETE` ပြပါမည်။ စစ်နေစဉ် သီးခြား deployment/schema change မလုပ်ပါနှင့်။

ဤ rehearsal အောင်မြင်ပြီးလည်း release မတိုင်မီ backup အသစ်ယူပြီး source/schema/config ကို ပြန်စစ်ရန်လိုပါသည်။

## နောက်ဆုံး Release အဆင့်

1. လက်ရှိ Live source နှင့် GitHub branch ကို တိုက်စစ်ပြီး Public Website / PartnerDynamics မပြောင်းကြောင်း စစ်ပါ။
2. သီးခြား Test/Preview မှာ လက်ရှိ Server နှင့် ကိုက်ညီသော dependencies ဖြင့် SQLite integration tests စမ်းပါ။ Production database ကို tests အတွက် မသုံးပါ။
3. Database/Config backup နှင့် သီးခြား Restore drill ပြီးစီးမှ Code/Migration release ပြင်ဆင်ပါ။ လက်ရှိ Student ဝင်ခွင့်နှင့် သက်တမ်းများကို မဖျက်ပါ။
4. အသစ်ထည့်ထားသော migration ကို apply ပြီး `PBR_PARTNER_PORTAL_ENABLED=true` ဖွင့်ပါ။ Partner AI private service မှ `knowledgeReady=true` နှင့် vectors ရှိကြောင်း စစ်ပါ။
5. Live Website ရဲ့ Login/Create Account ကနေ အကောင့်အသစ်ဖွင့်၊ Admin က ရက်အရေအတွက်ပေး၊ User ပြန်ဝင်ပြီး PBR AI ကို အမှန်တကယ် မေးကြည့်ပါ။ Saved Results နဲ့ PartnerDynamics ကိုလည်း မိမိအကောင့်ဖြင့် စမ်းပါ။

ဒီအဆင့်ရောက်မှ Live ချိတ်ဆက်မှုကို အမှန်တကယ် စမ်းနိုင်ပါမယ်။ Local Demo account ရွေးချယ်သည့်စနစ်ကို Live ပေါ် မတင်ပါ။ Gemini API key ကို Chat ထဲ မပို့ပါနှင့်။

# Partner Portal — Live ချိတ်ဆက်မှု စစ်ဆေးရန်

## မူလ Filament Admin ကို ဆက်သုံးသည့် Update

Admin Login ပြီးလျှင် မူလ `/admin` ကို ပြန်ရောက်ပါမည်။ Filament ရဲ့ Sidebar၊ Dashboard၊ Business / Access / Content အုပ်စုများနှင့် မူလ resources အားလုံး ဆက်ရှိပါမည်။ ပုံမှန် User သည် Portal အသစ်ကို ဆက်သုံးပါမည်။ Admin user menu မှ `Open User Portal` ကို ရွေးနိုင်ပါသည်။

- `Access → Accounts & Access` မှ Pending / Active / Expired / Suspended / Expiring in 7 days ကို စစ်နိုင်ပါသည်။ `Manage → Activate Portal Access` သို့မဟုတ် `Extend Portal Access` တွင် စိတ်ကြိုက်ရက်အရေအတွက် ပေးနိုင်ပါသည်။ `Suspend Portal Access` သည် Portal ဝင်ခွင့်ကို ယာယီရပ်ပြီး AI history / saved results မဖျက်ပါ။ မူလ Account Activate/Deactivate နှင့် Student 1 Year/Lifetime controls များ ဆက်ရှိပါသည်။
- `Access → Access History` တွင် ဘယ် Admin က ဘယ် account ကို ဘယ်အချိန် ဘယ်လိုပြောင်းခဲ့သလဲ ကြည့်နိုင်ပါသည်။ User တစ်ဦးချင်းစီ၏ `Manage → Access History` မှ တိုက်ရိုက်ကြည့်နိုင်ပါသည်။ ယခင်ထည့်ထားသော `/portal/admin/accounts` link သည် Filament ထဲကို လွှဲပေးပါသည်။
- `Content → Articles → Create` တွင် ခေါင်းစဉ်၊ အမျိုးအစား၊ အကျဉ်းချုပ်၊ စာကိုယ်နှင့် လိုအပ်လျှင် ပုံထည့်နိုင်ပါသည်။ `published_at` ကို ဗလာထားလျှင် Draft ဖြစ်ပါသည်။ ထိုင်းအချိန်ဖြင့် ဖော်ပြမည့်အချိန် ရောက်လျှင် Public Website ၏ Articles တွင် ပေါ်ပါမည်။
- `Content → Videos → Create` တွင် မူလအတိုင်း YouTube link သို့မဟုတ် ID ထည့်ပြီး Publish လုပ်နိုင်ပါသည်။ နောင်အချိန်ထားလျှင် ထိုအချိန်ရောက်မှ Public Videos တွင် ပေါ်ပါမည်။ မူလ Article/Video edit နှင့် publishing forms မပြောင်းပါ။

လက်ရှိ Live `450e7e3f0d765117692f14384ddd662982fd2911` မှ ပြင်ဆင်ထားသော commit သို့ update လုပ်ရန် `bash scripts/pbr-filament-update.sh REVIEWED_COMMIT_SHA` ကို server တွင် သုံးပါသည်။ Script ကို reviewed commit မှ download လုပ်ပြီး checksum စစ်ထားရပါမည်။ အောက်ပါ မူလ Portal installer ကို ထပ်မသုံးပါနှင့်။

Filament update သည် database migration၊ seeder၊ environment update မလုပ်ပါ။ သီးခြား SQLite တွင် full tests စမ်းပြီးမှ Maintenance ထားကာ လက်ရှိ Portal ၏ database/config/uploads ကို fresh backup နှင့် isolated restore drill လုပ်ပါသည်။ Actual Filament/Article/Video forms ကို web user ဖြင့် render စစ်ပြီး မူလ content၊ accounts၊ chats၊ access records မပြောင်းကြောင်း တိုက်စစ်ပါသည်။ Public HTTPS probe ကျလျှင် code ကို မူလ installed Portal သို့ ပြန်ထားပါသည်။ Output ၏ `PBR_FILAMENT_UPDATE=COMPLETE` ကို စစ်ပြီးမှ signed-in Admin publishing workflow ကို ဆက်စမ်းပါ။

GitHub CI သည် disposable MySQL fixture ပေါ်တွင် success/rollback နှစ်ခုလုံး စမ်းပါသည်။ Public DNS/HTTPS probes ကို synthetic test double ဖြင့် စမ်းသဖြင့် Live HTTPS အောင်မြင်ကြောင်း CI တစ်ခုတည်းဖြင့် မဆိုလိုပါ။ Browser visual QA နှင့် အမှန်တကယ် signed-in Live UAT သည် သီးခြားစစ်ဆေးရမည်။

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

2026-10-17 06:22 UTC တွင် user ပြန်ပို့သော Server output အရ Database restore/roundtrip၊ Application restore၊ checksum၊ source မပြောင်းခြင်းနှင့် ယာယီ database/account cleanup အားလုံး အောင်မြင်ပါသည်။ `PBR_PORTAL_BACKUP=COMPLETE` ဖြစ်ပြီး Portal သည် ပိတ်ထားဆဲ ဖြစ်ပါသည်။

### စစ်ဆေးပြီးသော commit ကို Live မှာ ဖွင့်ခြင်း

`sudo bash scripts/pbr-portal-install.sh TARGET_SHA VERIFIED_BACKUP_PATH /var/www/thepbr-laravel` ကိုသာ အသုံးပြုပါ။ `TARGET_SHA` သည် CI အောင်မြင်ပြီး စစ်ဆေးပြီးသော 40-character commit ဖြစ်ရပါမည်။ Moving branch name ကို release target အဖြစ် မသုံးပါ။

Installer သည် အောက်ပါအဆင့်များကို ဆောင်ရွက်ပါသည်။

1. Backup checksum၊ လက်ရှိ source၊ Public Website/PartnerDynamics မပြောင်းခြင်း၊ dependency မပြောင်းခြင်း၊ migration တစ်ခုတည်းသာ ထည့်ခြင်းကို စစ်ပါသည်။ လက်ရှိ cached configuration နှင့် `.env` မှ ပြန်တည်ဆောက်သော အဓိက configuration ကို သီးခြားတိုက်စစ်ပါသည်။ App key နှင့် AI secret ကို မလဲပါ။
2. သီးခြား source/vendor မိတ္တူပေါ်မှာ Server PHP ဖြင့် SQLite regression suite၊ Blade နှင့် JS စစ်ပါသည်။ Production `.env` ကို test မိတ္တူထဲ မကူးပါ။
3. မူလ `partner-ai` source version ကို စစ်ပြီး gateway မှ မေးခွန်းတစ်ခု တကယ်မေးပါသည်။ ပြည့်စုံသော answer နှင့် `mode=rag` ရရှိမှ ဆက်လုပ်ပါသည်။ အဖြေကို private release receipt ထဲသာ သိမ်းပြီး User account/chat record မဖန်တီးပါ။
4. Website ကို ခဏ Maintenance ထားပြီး fresh backup + isolated restore drill အသစ် လုပ်ပါသည်။ ထို database မိတ္တူပေါ်မှာ migration တစ်ခုတည်းကို သီးခြား account ဖြင့် စမ်းပြီး မူလ business/account/access records မပြောင်းကြောင်း တိုက်စစ်ပါသည်။
5. စစ်ဆေးပြီးသော commit ကိုသာ တင်၊ additive migration တစ်ခုတည်းကို apply လုပ်၊ Portal flag ဖွင့်ပြီး web process user ဖြင့် Portal/AI/Tools/Library/Admin templates ကို render စမ်းပါသည်။ မူလ records ကို ထပ်တိုက်စစ်ပါသည်။
6. Website ကို ပြန်ဖွင့်ပြီး public HTTPS pages၊ anonymous Portal login guard နှင့် public Portal CSS ကို စစ်ပါသည်။ အောင်မြင်ပါက `PBR_PORTAL_INSTALL=COMPLETE` ပြပါသည်။ ထို့နောက် အမှန်တကယ် signed-in workflow ကို User/Admin ဖြင့် စမ်းရန် ကျန်ပါသည်။

အမှားဖြစ်ပါက Maintenance ပြီးနောက် စတင်ပြောင်းထားသော code/config ကို မူလ baseline သို့ ပြန်ထားရန် ကြိုးစားပါသည်။ Database rollback/drop မလုပ်ပါ။ Portal tables/records အသစ်များကို ဆက်သိမ်းထားပါသည်။ `PORTAL_ROLLBACK=NEEDS_REVIEW` ပြပါက Website ကို Maintenance ထားပြီး output ကို ပြန်စစ်ပါ။ Backup/receipt/private logs များထဲတွင် account data/config ပါနိုင်သဖြင့် Chat ထဲ မပို့ပါနှင့်။ Terminal ၏ sanitized output ကိုသာ ပို့ပါ။

CI installer စမ်းသပ်မှုသည် disposable MySQL ပေါ်မှာ install၊ migration၊ real template rendering၊ account data preservation နှင့် rollback ကို စမ်းပါသည်။ Private AI HTTP/SSE protocol နှင့် public DNS/HTTPS probes ကိုသာ synthetic fixture ဖြင့် စမ်းပါသည်။ Live model generation၊ public HTTPS နှင့် browser visual QA အောင်မြင်ပြီးဟု CI ရလဒ်ကို မယူဆပါ။

Installer သည် exact commit ဖြင့် detached HEAD သို့ တင်ပါသည်။ GitHub main/PR ကို အလိုအလျောက် merge မလုပ်ပါ။ Server receipt နှင့် User workflow စစ်ဆေးပြီးမှ repository release ကို ဆက်လက်ပိတ်သိမ်းပါမည်။

## နောက်ဆုံး Release အဆင့်

1. လက်ရှိ Live source နှင့် GitHub branch ကို တိုက်စစ်ပြီး Public Website / PartnerDynamics မပြောင်းကြောင်း စစ်ပါ။
2. သီးခြား Test/Preview မှာ လက်ရှိ Server နှင့် ကိုက်ညီသော dependencies ဖြင့် SQLite integration tests စမ်းပါ။ Production database ကို tests အတွက် မသုံးပါ။
3. Database/Config backup နှင့် သီးခြား Restore drill ပြီးစီးမှ Code/Migration release ပြင်ဆင်ပါ။ လက်ရှိ Student ဝင်ခွင့်နှင့် သက်တမ်းများကို မဖျက်ပါ။
4. အသစ်ထည့်ထားသော migration ကို apply ပြီး `PBR_PARTNER_PORTAL_ENABLED=true` ဖွင့်ပါ။ Partner AI private service မှ `knowledgeReady=true` နှင့် vectors ရှိကြောင်း စစ်ပါ။
5. Live Website ရဲ့ Login/Create Account ကနေ အကောင့်အသစ်ဖွင့်၊ Admin က ရက်အရေအတွက်ပေး၊ User ပြန်ဝင်ပြီး PBR AI ကို အမှန်တကယ် မေးကြည့်ပါ။ Saved Results နဲ့ PartnerDynamics ကိုလည်း မိမိအကောင့်ဖြင့် စမ်းပါ။

ဒီအဆင့်ရောက်မှ Live ချိတ်ဆက်မှုကို အမှန်တကယ် စမ်းနိုင်ပါမယ်။ Local Demo account ရွေးချယ်သည့်စနစ်ကို Live ပေါ် မတင်ပါ။ Gemini API key ကို Chat ထဲ မပို့ပါနှင့်။

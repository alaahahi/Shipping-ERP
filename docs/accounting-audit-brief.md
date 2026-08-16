# موجز تدقيق المحاسبة — Shipping-ERP

> **الغرض:** وثيقة تقنية دقيقة لمراجعة نظام المحاسبة (كشف الحساب + دليل الحسابات + القيد المزدوج) بواسطة نموذج ذكاء اصطناعي آخر.  
> **المصدر:** الكود الفعلي في المستودع (لا تخمين).  
> **قاعدة ذهبية للمشروع:** الأرصدة **لا تُحدَّث مباشرة**؛ تُشتق دائماً من `journal_lines` المرتبطة بقيود `journal_entries` بحالة `posted`.

---

## 1) نظرة عامة على النظام

Shipping-ERP يستخدم محاسبة قيد مزدوج (Double-Entry) مركزية:

| الطبقة | الجداول / الكيانات |
|--------|---------------------|
| دليل الحسابات | `accounts` — نموذج `App\Models\Account` |
| رأس القيد | `journal_entries` — نموذج `App\Models\JournalEntry` |
| بنود القيد | `journal_lines` — نموذج `App\Models\JournalLine` |
| الخدمة المركزية | `App\Services\JournalService` |
| كشف الحساب / الرصيد | `App\Services\AccountService` (`ledger`, `balance`, `signedBalance`) |

**حالات القيد** (`App\Enums\JournalStatus`):

- `draft` — مسودة؛ قابلة للتعديل؛ **لا تدخل** في الأرصدة.
- `posted` — مرحّل؛ يدخل في كشف الحساب والأرصدة.
- `void` — ملغى؛ **يخرج** من الأرصدة (يبقى السجل للتاريخ؛ لا يُحذف soft-delete تلقائياً عند الإلغاء).

**رقم السند:** `JV-YYYYMM-####` عبر `JournalService::nextVoucherNumber()` (يشمل السجلات المحذوفة soft عند الترقيم).

**Soft Deletes:** موجودة على `accounts`, `journal_entries`, `journal_lines`. استعلامات الكشف تستبعد المحذوف عبر `whereNull(deleted_at)` على السطر والقيد.

**لا يوجد عمود `balance` على جدول `accounts`.** أي رصيد معروض هو حساب مشتق.

---

## 2) هيكل دليل الحسابات (Chart of Accounts)

### 2.1 الجدول والحقول

Migration: `database/migrations/2026_07_14_132500_create_accounts_table.php`

| حقل | معنى |
|-----|------|
| `code` | رمز فريد (32) |
| `name` | الاسم |
| `type` | `asset` / `liability` / `equity` / `revenue` / `expense` (`App\Enums\AccountType`) |
| `currency` | عادة `USD` أو `AED` |
| `parent_id` | شجرة حسابات |
| `accountable_type` / `accountable_id` | morph لربط حساب فرعي بكيان (مثل شركة) |
| `is_system` | حساب نظامي — لا يُحذف؛ رمز/نوع/عملة محمية عند التحديث |
| `is_active` | نشط |
| `show_on_dashboard` | اختصار لوحة التحكم |
| `deleted_at` | soft delete |

البذر: `Database\Seeders\ChartOfAccountsSeeder` → يستدعي `AccountService::seedChartOfAccounts()`.

### 2.2 الحسابات النظامية (الأكواد الحقيقية)

من `AccountService::seedChartOfAccounts()`:

| Code | Name | Type | Currency | Parent |
|------|------|------|----------|--------|
| 1000 | Assets | asset | USD | — |
| 1100 | Cash | asset | USD | 1000 |
| 1200 | Bank | asset | USD | 1000 |
| 1300 | Dubai Account | asset | **AED** | 1000 |
| 1400 | Iran Account | asset | USD | 1000 |
| 1500 | Ship Clearing | asset | **AED** | 1000 |
| 1600 | Accounts Receivable | asset | USD | 1000 |
| 1660 | Iran Cars Receivable | asset | USD | 1000 |
| 2000 | Liabilities | liability | USD | — |
| 2100 | Accounts Payable | liability | USD | 2000 |
| 2210 | Ship Partner Clearing | liability | USD | 2000 |
| 2215 | Ship Partner Clearing AED | liability | AED | 2000 |
| 3000 | Equity | equity | USD | — |
| 4000 | Revenue | revenue | USD | — |
| 4100 | Shipping Revenue | revenue | USD | 4000 |
| 4200 | Land Transit Revenue | revenue | USD | 4000 |
| 4300 | Iran Cars Revenue | revenue | USD | 4000 |
| 5000 | Expenses | expense | USD | — |
| 5100 | Voyage Expenses | expense | USD | 5000 |
| 5110 | Ship Expenses USD | expense | USD | 5000 |
| 5200 | Ship Expenses | expense | **AED** | 5000 |
| 5300 | Captain Commission | expense | USD | 5000 |
| 5310 | Captain Commission AED | expense | **AED** | 5000 |

### 2.3 حسابات فرعية ديناميكية

**ذمم الشركات (Company AR) — تحت 1600**

- الخدمة: `App\Services\CompanyReceivableAccountService`
- ثابت التحكم: `CONTROL_CODE = '1600'`
- الرمز المفضّل: `1600-{company_id}` بأربعة أرقام (`1600-0007`)
- يُربط عبر `companies.ar_account_id` + morph `accountable` إلى `Company`
- يُنشأ/يُزامَن عبر `ensureFor()` / `resolveFor()`

**ذمم سيارات إيران — تحت 1660**

- الخدمة: `App\Services\IranCarReceivableAccountService`
- ثابت: `CONTROL_CODE = '1660'`
- الرمز: `1660-{company_id}`
- الربط: `companies.iran_ar_account_id`
- إيراد البيع: حساب `4300`

### 2.4 طبيعة الرصيد (Normal balance)

`AccountType::isDebitNormal()`:

- **مدين طبيعي (debit − credit):** `asset`, `expense`
- **دائن طبيعي (credit − debit):** `liability`, `equity`, `revenue`

تُستخدم في `AccountService::signedFromTotals()`.

### 2.5 قواعد إدارة الدليل

- الأب يجب أن يطابق النوع والعملة (`assertParentCompatible`).
- حذف الحساب ممنوع إذا: `is_system`، أو له `journalLines`، أو له أبناء، أو حساب ذمم شركة مرتبط.
- حسابات Company AR: لا يُغيَّر code/type/currency/parent عند التحديث.

**واجهة الدليل:** Inertia `Accounts/Index` — routes تحت `/accounts` (انظر خريطة المسارات).

---

## 3) قواعد القيد المزدوج والثوابت (Invariants)

الخدمة: `App\Services\JournalService`

### 3.1 ما يجب أن يتحقق دائماً عند الإنشاء/التحديث/الترحيل

1. **عدد البنود ≥ 2**
2. **مجموع المدين = مجموع الدائن** (بعد `round(..., 2)` لكل بند ثم للمجموع)
3. **المبلغ الكلي > 0**
4. كل بند: إما مدين أو دائن — **ليس كلاهما**، وليس صفراً لكليهما
5. كل حساب في البنود: موجود، نشط، وعملته = عملة رأس القيد
6. الترحيل (`post`) فقط من `draft`؛ يعيد التحقق من التوازن
7. الإلغاء (`void`) فقط من `posted`؛ يضبط `voided_by` / `voided_at` / `void_reason` ويسجّل Log
8. بعد الترحيل: تعديل الوصف/المرفق فقط عبر `updatePostedMeta` — **ممنوع** تغيير المبالغ أو الحسابات

### 3.2 حقول البنود ذات الصلة المحاسبية

Migration الأساس: `journal_lines` + إضافات لاحقة لـ `company_id`, `voyage_id`, `owner_id`

| حقل | استخدام |
|-----|---------|
| `debit` / `credit` | decimal(18,2) |
| `account_id` | الحساب |
| `company_id` | لربط كشف ذمم الشركة |
| `voyage_id` | تخصيص رحلة (إيراد / قبض) |
| `owner_id` | شركاء السفينة (clearing) |
| `memo` | مذكرة السطر |

### 3.3 ما يجب ألا يحدث أبداً

| ممنوع | السبب / أين يُفرض |
|-------|-------------------|
| تحديث رصيد مخزّن على الحساب | لا يوجد عمود رصيد؛ `balance()` مشتق |
| قيد غير متوازن | `assertBalancedLines` |
| بند مدين ودائن معاً | `syncLines` |
| ترحيل حسابات بعملات مختلفة داخل نفس السند | `assertAccountsMatchCurrency` |
| احتساب draft/void في كشف الحساب | فلتر `status = posted` فقط |
| تعديل مبالغ قيد مرحّل | `updatePostedMeta` وصف/مرفق فقط |
| حذف حساب نظامي أو حساب له حركة | `AccountService::delete` |

---

## 4) كيف يُبنى كشف الحساب (Account Statement / Ledger)

### 4.1 المسار والواجهة

- Route: `GET /accounts/{account}` → `accounts.show`
- Controller: `App\Http\Controllers\AccountController::show`
- Service: `AccountService::ledger($account, $filters, $perPage = 50)`
- Vue: `resources/js/Pages/Accounts/Show.vue`
- تصدير: `accounts.export.excel` / `accounts.export.pdf` عبر `AccountLedgerExportService`

### 4.2 الاستعلام الأساسي (`postedLinesQuery`)

من `journal_lines` JOIN `journal_entries` حيث:

1. `journal_lines.account_id` = الحساب المطلوب  
2. `journal_lines.deleted_at IS NULL`  
3. `journal_entries.deleted_at IS NULL`  
4. `journal_entries.status = 'posted'`  
5. فلاتر اختيارية:
   - `date_from` / `date_to` على `entry_date`
   - `beforeDate` لرصيد الافتتاح (`entry_date < date_from`)
   - بحث: `voucher_number`, `description`/`memo`, مبلغ يطابق debit أو credit
6. ترتيب: `entry_date` → `journal_entries.id` → `journal_lines.id`

### 4.3 الرصيد الموقعّ و Running Balance

```
signed = isDebitNormal ? (debit - credit) : (credit - debit)
```

- **رصيد الحساب الكامل:** `balance()` → `signedBalance()` بدون فلتر تاريخ (كل الـ posted).
- **Opening:** إذا وُجد `date_from` **ولم** يكن هناك بحث نصي/مبلغ → رصيد كل القيود قبل `date_from`.  
  إذا وُجد بحث → opening = `0` (عن قصد؛ حتى لا يختلط الرصيد الجاري مع نتائج مفلترة).
- **Running:** يبدأ من opening، ثم يضيف signed لكل سطر (مع تصحيح ترقيم الصفحات عبر جمع أسطر الصفحات السابقة).
- **Closing:** opening + signed(مجموع مدين/دائن الفترة المفلترة).

### 4.4 إحصاءات الفترة المعروضة في الواجهة

`AccountController::show` يمرّر:

- `account.balance` — الرصيد الكامل (ليس بالضرورة رصيد الفترة)
- `period_debit`, `period_credit`
- `period_net` = **credit − debit** (مجموع حركات الفترة كناتج خام؛ انظر الاختبارات)
- قائمة `lines` مع `running balance` لكل سطر و`counterpart` (أول حساب آخر في نفس القيد)

ملاحظة: `ledger()` يحسب أيضاً `opening_balance` و`closing_balance` ويُستخدمان في التصدير؛ صفحة Show الحالية تعتمد على `account.balance` + إحصاءات الفترة.

### 4.5 حركات يدوية من كشف الحساب

`POST /accounts/{account}/movements` → `AccountService::postMovement`:

- **Receipt:** Dr الحساب الحالي / Cr الطرف المقابل  
- **Payment:** Dr الطرف / Cr الحساب الحالي  
- نفس العملة؛ مبلغ > 0؛ يُنشأ draft ثم يُرحَّل فوراً  

إلغاء حركة من الكشف: `POST .../journals/{journal}/void` → `JournalService::void` (تختفي من الكشف لأنها لم تعد `posted`).

### 4.6 كشف ذمم الشركة (منفصل عن دليل الحسابات العام)

`CompanyLedgerService::statement(Company)`:

- أسطر `journal_lines` حيث `company_id` = الشركة  
- و`account_id` ضمن 1600 + أبنائه (+ `ar_account_id` إن وُجد)  
- قيد `posted` وعملة USD  
- Running: `debit - credit` (طبيعة أصل)  
- يُعرض في `companies.show`

---

## 5) حدود الوحدات: ماذا يرحّل إلى المحاسبة وماذا لا؟

### 5.1 يرحّل إلى Journal (محاسبة رسمية)

| الوحدة | الخدمة | القيد النموذجي | Reference نمطي |
|--------|--------|----------------|----------------|
| حركة يدوية من الحساب | `AccountService::postMovement` | Receipt/Payment ثنائي الطرف | — |
| قيد يدوي عام | `JournalEntryController` + `JournalService` | أي حسابات متوازنة | — |
| إيراد رحلة بحرية | `VoyageSettlementPostingService::postRevenue` | Dr شركة AR (فرعي 1600) ×N / Cr **4100** | `VREV-{voyage_id}` |
| عمولة الربان | `VoyageSettlementPostingService::postCommission` | Dr **5310** / Cr حساب دفع AED | `VCOM-{voyage_id}` |
| مصروف رحلة | `VoyageExpensePostingService` | Dr **5100**(USD) أو **5200**(AED) / Cr حساب دفع | `VEXP-{id}` |
| مصروف سفينة | `ShipExpensePostingService` | Dr **5110**(USD) أو **5200**(AED) / Cr نقد أو **2210/2215** | `SEXP-{id}` |
| دفعة شريك سفينة | `ShipPartnerContributionPostingService` | Dr نقد/بنك / Cr **2210 أو 2215** | `SPAY-{id}` |
| شحن بري (Land Trip) | `LandTripPostingService` | Dr شركة AR / Cr **4200** (USD فقط) | `LCMR-{id}` |
| سند قبض/صرف نقدي | `MoneyVoucherService::post` | Receipt: Dr دفع / Cr AR فرعي؛ Payment: Dr **2100** / Cr دفع — **USD فقط** | رقم السند MV |
| ذمة مباشرة على شركة | `CompanyDirectChargeService` | Dr AR فرعي / Cr إيراد أو التزام/حقوق (افتراضي **4100**) | `CDC-...` |
| بيع سيارة إيران | `IranCarService` (فاتورة) | Dr **1660-xxxx** / Cr **4300** | VIN |
| تحصيل سيارة إيران | `IranCarPaymentService` | Dr **1100/1200** (أو فرعي) / Cr Iran AR | `ICP-...` |
| تحصيل مجمّع إيران | `IranCarPoolPaymentService` | نفس منطق التحصيل على مستوى الشركة | `ICPP-...` |

**حسابات الدفع المسموحة** (`ResolvesExpensePaymentAccounts::paymentAccountOptions`):

- USD: `1100`, `1200`, `1400`, `2100`
- AED: `1300`, `1500`

**إيران تحصيل نقدي:** فقط `1100` / `1200` وأبناؤهما (`IranCarService::cashBankAccountOptions`).

### 5.2 لا يرحّل إلى Journal (تشغيلي فقط)

| الوحدة | الجداول / الخدمة | ملاحظة للتدقيق |
|--------|------------------|----------------|
| **Company Wallet** | `company_wallet_entries` + `CompanyWalletService` | رصيد من SUM(deposit − withdrawal). **لا** `JournalService`. مسارات `land-trips.companies.wallet.*` |
| **Dubai Partners SOA** | `dubai_partners`, `dubai_account_entries`, `dubai_cars` + `DubaiAccountService` | كشف تشغيلي AED بـ debit/credit خاص؛ **لا** يكتب قيوداً على حساب COA **1300**. حساب **1300** في الدليل هو أصل دفع AED منفصل |
| ملخص شركاء السفينة في واجهة السفينة | حسابات تشغيلية من مصروفات/مساهمات | الترحيل المحاسبي يتم فقط عند `post` عبر خدمات Posting أعلاه |
| `ShipPartnerSettlementService` | لا يستدعي Journal | تسوية/عرض تشغيلي |

**تمييز مهم:**  
- **1300 Dubai Account (COA)** = حساب محاسبي رسمي (AED asset).  
- **وحدة Dubai Accounts (شركاء دبي)** = دفتر تشغيلي منفصل — لا تخلط بينهما عند التدقيق.

---

## 6) Soft delete / Void / Posting status — ملخص سلوكي

| الحدث | الأثر على كشف الحساب |
|-------|----------------------|
| قيد `draft` | غير مرئي في الرصيد |
| قيد `posted` | مرئي ويؤثر على الرصيد |
| `void` لقيد مرحّل | يختفي من الرصيد فوراً (status ≠ posted)؛ الأسطر تبقى غير محذوفة عادةً |
| Soft-delete لسطر/قيد | يُستبعد بـ `whereNull(deleted_at)` |
| حذف دفعة إيران/مجمّع | `void` للقيد المرتبط ثم soft-delete للدفع |
| تعديل وصف قيد مرحّل | لا يغيّر الرصيد |

ربط الوحدات بالقيود عبر أعمدة مثل:

- `voyages.revenue_journal_entry_id`, `commission_journal_entry_id`
- `voyage_expenses.journal_entry_id`
- `ship_expenses.journal_entry_id`
- `ship_partner_contributions.journal_entry_id`
- `land_trips.journal_entry_id`
- `money_vouchers.journal_entry_id`
- `company_direct_charges.journal_entry_id`
- `iran_cars.invoice_journal_id`
- `iran_car_payments.journal_entry_id` / pool payments

إعادة الترحيل: عدة خدمات ترفض إن وُجد قيد مرتبط وغير `void`؛ بعضها يصفّر الـ id ثم يعيد الإنشاء بعد void.

---

## 7) خريطة الملفات والمسارات (للمراجع)

### 7.1 نواة المحاسبة

| مسار الملف | دور |
|------------|-----|
| `app/Models/Account.php` | نموذج الدليل |
| `app/Models/JournalEntry.php` | رأس القيد |
| `app/Models/JournalLine.php` | بنود القيد |
| `app/Enums/AccountType.php` | أنواع + طبيعة الرصيد |
| `app/Enums/JournalStatus.php` | draft/posted/void |
| `app/Services/JournalService.php` | توازن، ترحيل، إلغاء، سندات |
| `app/Services/AccountService.php` | دليل، رصيد، كشف، حركات، بذر |
| `app/Services/AccountLedgerExportService.php` | Excel/PDF لكشف الحساب |
| `app/Services/CompanyReceivableAccountService.php` | فروع 1600 |
| `app/Services/IranCarReceivableAccountService.php` | فروع 1660 + 4300 |
| `app/Services/CompanyLedgerService.php` | كشف ذمم الشركة |
| `app/Services/Concerns/ResolvesExpensePaymentAccounts.php` | حسابات الدفع المسموحة |
| `app/Http/Controllers/AccountController.php` | CRUD دليل + كشف |
| `app/Http/Controllers/JournalEntryController.php` | قيود يدوية |
| `app/Policies/JournalEntryPolicy.php` | صلاحيات ترحيل/إلغاء |
| `database/seeders/ChartOfAccountsSeeder.php` | بذر الدليل |
| `resources/js/Pages/Accounts/Show.vue` | UI كشف الحساب |
| `resources/js/Pages/Accounts/Index.vue` | UI الدليل |
| `resources/views/reports/account-ledger-pdf.blade.php` | PDF |

### 7.2 ترحيل الوحدات

| ملف |
|-----|
| `app/Services/VoyageSettlementPostingService.php` |
| `app/Services/VoyageExpensePostingService.php` |
| `app/Services/ShipExpensePostingService.php` |
| `app/Services/ShipPartnerContributionPostingService.php` |
| `app/Services/LandTripPostingService.php` |
| `app/Services/MoneyVoucherService.php` |
| `app/Services/CompanyDirectChargeService.php` |
| `app/Services/IranCarService.php` (فاتورة البيع) |
| `app/Services/IranCarPaymentService.php` |
| `app/Services/IranCarPoolPaymentService.php` |

### 7.3 خارج المحاسبة الرسمية (للتمييز)

| ملف |
|-----|
| `app/Services/CompanyWalletService.php` |
| `app/Services/DubaiAccountService.php` |
| `database/migrations/2026_08_14_230000_create_company_wallet_entries.php` |
| `database/migrations/2026_07_19_160000_create_dubai_accounts_tables.php` |

### 7.4 Routes مهمة (`routes/web.php`)

| Name | Path تقريبي |
|------|-------------|
| `accounts.index` / `accounts.show` | `/accounts`, `/accounts/{account}` |
| `accounts.movements.store` | POST `/accounts/{account}/movements` |
| `accounts.journals.void` | POST `/accounts/{account}/journals/{journal}/void` |
| `accounts.export.excel` / `.pdf` | تصدير الكشف |
| `journals.*` | `/journals` CRUD + post + void |
| `money-vouchers.*` | سندات قبض/صرف |
| `companies.show` | يتضمن ledger ذمم الشركة |
| `companies.direct-charges.store` | ذمة مباشرة |
| `voyages.settlements.post-revenue` / `post-commission` | ترحيل تسوية رحلة |
| `voyages.expenses.post` | مصروف رحلة |
| `ships.expenses.post` | مصروف سفينة |
| `ships.contributions.post` | دفعة شريك |
| `land-trips.post` | ترحيل شحن بري |
| `land-trips.companies.wallet.*` | محفظة تشغيلية فقط |
| `dubai-accounts.*` | SOA دبي تشغيلي |

### 7.5 اختبارات مرجعية للسلوك المتوقع

- `tests/Feature/AccountMovementTest.php` — receipt/payment، running balance، void يخرج من الكشف، تصدير
- `tests/Feature/CompanyReceivableAccountingTest.php`
- `tests/Feature/IranCarAccountingTest.php`

---

## 8) قائمة تحقق لـ AI المراجع (Verification Checklist)

استخدم هذه الأسئلة ضد الكود و/أو بيانات حقيقية. أي إجابة «لا» أو «غير واضح» = مرشّح لخطأ محاسبي.

### أ) سلامة القيد المزدوج

1. هل يوجد أي مسار يكتب إلى `journal_lines` دون المرور بـ `JournalService::assertBalancedLines` (أو مكافئ صارم)؟
2. هل يمكن أن يوجد قيد `posted` بمجموع مدين ≠ دائن؟
3. هل يمكن بند واحد أن يحمل debit و credit معاً بعد الحفظ؟
4. هل تُرفض الحسابات ذات العملة المختلفة عن رأس القيد؟
5. هل رقم السند `JV-...` فريد حتى مع soft-deleted؟

### ب) الأرصدة وكشف الحساب

6. هل جدول `accounts` خالٍ من عمود رصيد مخزّن يُحدَّث يدوياً؟
7. هل `AccountService::balance` و`ledger` يستخدمان فقط `status = posted` ويستبعدان soft-deleted؟
8. هل إلغاء (`void`) حركة من الكشف يزيل أثرها من `account.balance`؟
9. هل running balance يحترم طبيعة الحساب (`isDebitNormal`)؟
10. عند فلتر `date_from` بدون بحث: هل opening = رصيد ما قبل التاريخ؟
11. عند وجود بحث voucher/description/amount: هل opening يُصفَّر عمداً؟
12. هل ترقيم الصفحات يصحّح running عبر مجموع أسطر الصفحات السابقة (`priorTotals` + offset)؟

### ج) دليل الحسابات

13. هل أكواد النظام أعلاه موجودة بعد `ChartOfAccountsSeeder` بالعملات الصحيحة (خصوصاً 1300/1500/5200/5310/2215 = AED)؟
14. هل فروع الشركات تحت 1600 تُنشأ كـ `1600-####` وترتبط بـ `ar_account_id`؟
15. هل فروع إيران تحت 1660 منفصلة عن 1600 ولا تُخلط في `CompanyLedgerService` (الذي يستخدم receivableAccountIds لـ 1600 فقط)؟

### د) حدود الوحدات

16. هل `CompanyWalletService` يستدعي `JournalService`؟ (المتوقع: **لا**)
17. هل `DubaiAccountService` يكتب `journal_entries`؟ (المتوقع: **لا**)
18. هل ترحيل Land Trip يقتصر على USD و Dr AR / Cr 4200؟
19. هل Money Voucher للـ AR/AP يقتصر على USD (1600 فرعي / 2100)؟
20. هل إيراد الرحلة يوزّع مديناً على كل شركة حسب `due_usd` ويجمع دائناً على 4100 بمبلغ `revenue_usd`؟
21. هل مصروف السفينة بوضع partner يدين 5110/5200 ويدائن 2210/2215 مع `owner_id`؟
22. هل بيع إيران يدين 1660-فرع ويدائن 4300؛ والتحصيل يعكس ذلك على نقد/بنك؟

### هـ) تماسك البيانات

23. لكل سجل تشغيلي مرحّل: هل `journal_entry_id` يشير لقيد `posted` غير void؟
24. هل إعادة الترحيل بعد void تمنع القيد المزدوج (لا قيدان posted لنفس الحدث)؟
25. هل مجموع مدين جميع الأسطر posted = مجموع دائنها على مستوى النظام (اختبار سلامة عامة)؟
26. هل كشف الشركة (`CompanyLedgerService`) يطابق مجموع حركات حسابها الفرعي 1600-xxxx لحركات `company_id` نفسها؟

### و) ما يجب الإبلاغ عنه كخطأ إن وُجد

- أي `UPDATE accounts SET balance=...` أو حقل رصيد مخزّن يُحدَّث من منطق الأعمال.
- قيد مرحّل غير متوازن.
- ظهور draft/void في كشف الحساب.
- خلط رصيد Wallet أو Dubai SOA مع رصيد حسابات COA.
- ترحيل cross-currency داخل سند واحد.
- إيراد/مصروف مرحّل مرتين لنفس الكيان التشغيلي دون void للسابق.

---

## 9) ملخص سريع للمراجع

1. **مصدر الحقيقة المحاسبية** = `journal_entries` (`posted`) + `journal_lines`.  
2. **كشف الحساب** = تجميع أسطر الحساب من القيود المرحّلة مع running balance حسب طبيعة الحساب.  
3. **دليل الحسابات** = شجرة أكواد نظامية + فروع ذمم شركات (1600) وإيران (1660).  
4. **Wallet ودفتر شركاء دبي** = تشغيليان؛ ليسا دفتر أستاذ عام.  
5. **لا أرصدة مباشرة**؛ أي انحراف عن ذلك خطأ تصميمي/تنفيذي.

---

*أُنشئت هذه الوثيقة من قراءة الكود في `C:\xampp\htdocs\Shipping-ERP` لأغراض التدقيق فقط — دون تغيير منطق المحاسبة.*

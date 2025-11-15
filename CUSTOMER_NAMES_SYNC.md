# 👤 Customer Names & Personalized Welcome

## New Feature - Personalized Greetings with Customer Names!

The bot now greets customers by name when they say "hello" or "hi"!

---

## 🎯 How It Works

### 1. Customer Names are Stored From Multiple Sources

**Source 1: Orders**
- When a customer places an order, they enter their name
- Name is saved to the database automatically
- Example: "Mohamad el hajj"

**Source 2: Brains ERP Sync**
- Admin can sync customer names from Brains ERP
- Matches WhatsApp phone numbers with Brains accounts
- Updates customer names automatically
- API: `http://194.126.6.162:1980/api/accounts?type=1&accocode=41110`

### 2. Personalized Welcome Messages

When a customer says "hello" or "hi":

**With Name:**
```
Hello Mohamad el hajj! 👋

Welcome to Librarie Memoires 📚

How can I help you today?

• 📖 Type products to see available books
• 📦 Type my orders to view your orders
• 💰 Type account to check your balance
• ❓ Type help for more information
```

**Without Name:**
```
Hello! 👋

Welcome to Librarie Memoires 📚
...
```

---

## 🔄 Syncing Customer Names from Brains

### Admin Dashboard Page

**URL:** `http://your-domain.com/admin/sync-customers.php`

**Features:**
- Shows statistics: Total customers, With names, Without names
- One-click sync from Brains ERP
- Real-time sync results
- Automatic phone number matching

### How to Sync

1. **Log in to Admin Dashboard**
2. **Go to "Sync Customers" page**
3. **Click "🔄 Sync Customer Names from Brains"**
4. **See Results:**
   ```
   ✅ Sync completed! Total accounts: 667 | Matched: 15 | Updated: 12
   ```

### What Happens During Sync

1. Fetches all customer accounts from Brains API
2. For each Brains account:
   - Extracts phone number from `Telephone` field
   - Cleans and normalizes phone number (+961 format)
   - Searches for matching WhatsApp customer
3. If match found:
   - Updates customer name from `Description` field
   - Updates email if empty
   - Updates address if empty
4. Shows summary of matched and updated customers

---

## 📞 Phone Number Matching

### Supported Formats

The sync script handles these phone formats from Brains:

| Brains Format | Cleaned | Final Format |
|---------------|---------|--------------|
| `09/851721` | `09851721` | `+96109851721` |
| `03/296030` | `03296030` | `+96103296030` |
| `09/921223/4 03/201087` | `09921223`, `03201087` | `+96109921223`, `+96103201087` |
| `03615802` | `03615802` | `+96103615802` |

### Matching Logic

```
1. Extract all phone numbers from Telephone field
2. Clean (remove slashes, spaces)
3. Convert to +961 format
4. Search in customers table
5. If match found, update name
```

---

## 🌍 Multi-Language Support

Welcome messages with names work in all 3 languages:

**English:**
```
Hello Mohamad el hajj! 👋
Welcome to Librarie Memoires 📚
```

**Arabic:**
```
مرحباً Mohamad el hajj! 👋
أهلاً بك في Librarie Memoires 📚
```

**French:**
```
Bonjour Mohamad el hajj! 👋
Bienvenue à Librarie Memoires 📚
```

---

## 💻 Technical Details

### Database Structure

**customers table:**
```sql
- id
- phone (+961 format)
- name (from orders or Brains sync)
- email
- address
- created_at
```

### API Endpoint

**Brains Customer Accounts API:**
```
GET http://194.126.6.162:1980/api/accounts?type=1&accocode=41110
```

**Response Format:**
```json
{
  "Success": true,
  "Content": [
    {
      "AccountCode": "000550",
      "Description": "Mme Dabbous May",
      "Telephone": "09/921223/4 03/201087",
      "Email": "",
      "Address": "9eme-6eme-1ere-TermS"
    }
  ]
}
```

### Code Flow

**MessageController.php:**
```php
if ($this->isGreeting($messageLower)) {
    $this->conversationState->clear($customer['id']);
    return ResponseTemplates::welcome($lang, $customer['name']);
}
```

**ResponseTemplates.php:**
```php
public static function welcome($lang, $customerName = null) {
    $greeting = $customerName ? [
        'ar' => "مرحباً {$customerName}!",
        'en' => "Hello {$customerName}!",
        'fr' => "Bonjour {$customerName}!"
    ][$lang] : [
        'ar' => "مرحباً!",
        'en' => "Hello!",
        'fr' => "Bonjour!"
    ][$lang];

    return $greeting . "\n\n" . $welcomeMessage;
}
```

---

## 🧪 Testing

### Command Line Sync

```bash
php /var/www/whatsapp-bot/admin/sync-customer-accounts.php
```

**Output:**
```
🔄 Syncing Customer Accounts from Brains ERP
=============================================

📥 Fetching customer accounts from Brains API...
✅ Fetched 667 accounts from Brains

✅ Updated: +96103296030 → Mme Khoury Tania
✅ Updated: +96109854884 → Mme Bassil Jocelyne
✅ Updated: +96103212252 → Mr Abi Nader Claude

📊 Sync Summary:
   Total Accounts: 667
   Matched Customers: 15
   Updated Records: 12

✅ Sync completed successfully!
```

### Test Welcome Message

Send "hi" or "hello" from WhatsApp to see personalized greeting!

---

## 📋 Use Cases

### Use Case 1: New Customer Orders

1. Customer places first order
2. Enters name: "John Doe"
3. Name saved to database
4. Next time: "Hello John Doe! 👋"

### Use Case 2: Existing Brains Customer

1. Customer in Brains ERP: "Mme Dabbous May"
2. Customer sends WhatsApp message from phone: +96109921223
3. Admin runs sync
4. System matches phone number
5. Updates name in database
6. Next time: "Hello Mme Dabbous May! 👋"

### Use Case 3: No Name Available

1. New customer sends first message
2. No order history, no Brains match
3. Generic greeting: "Hello! 👋"
4. After ordering or sync: "Hello [Name]! 👋"

---

## ✅ Benefits

**For Customers:**
- ✅ Personalized experience
- ✅ Feels like talking to a human
- ✅ Professional service
- ✅ Name recognition

**For Business:**
- ✅ Better customer engagement
- ✅ Professional appearance
- ✅ Automatic name sync from ERP
- ✅ No manual data entry

**For Admin:**
- ✅ One-click sync from Brains
- ✅ Automatic matching by phone
- ✅ Statistics dashboard
- ✅ Easy monitoring

---

## 🎯 Summary

✅ **Automatic Name Detection** - From orders or Brains sync
✅ **Personalized Greetings** - "Hello [Name]!" in 3 languages
✅ **Brains ERP Integration** - One-click sync from accounts API
✅ **Phone Number Matching** - Intelligent matching algorithm
✅ **Admin Dashboard** - Easy sync with statistics
✅ **Multi-Source** - Names from orders or ERP
✅ **Real-Time** - Works immediately after order or sync

Your WhatsApp bot now provides a personalized, professional customer experience! 🚀

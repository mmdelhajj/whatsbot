# 🗑️ Admin Dashboard - Delete Functions

## New Feature - Delete Data from Admin Dashboard!

Admin can now delete individual items or bulk delete all data with confirmation dialogs!

---

## 🎯 Available Delete Functions

### 1. Orders Management (`/admin/orders.php`)

**Delete Individual Order:**
- Click "🗑️ Delete" button next to any order
- Confirms: "Are you sure you want to delete order #WA-20251028-7180?"
- Deletes order and all related order items
- Shows success message

**Delete All Orders:**
- Click "🗑️ Delete All Orders" button at top right
- Confirms: "Are you sure you want to DELETE ALL ORDERS? This cannot be undone!"
- Clears entire orders and order_items tables
- Shows success message

---

### 2. Messages Management (`/admin/messages.php`)

**Delete All Messages:**
- Click "🗑️ Delete All Messages" button at top right
- Confirms: "Are you sure you want to DELETE ALL MESSAGES? This cannot be undone!"
- Clears entire messages table
- Shows success message

**Note:** Individual message delete not implemented (typically messages are managed in bulk)

---

### 3. Customers Management (`/admin/customers.php`)

**Delete Individual Customer:**
- Click "🗑️ Delete" button in Actions column
- Confirms: "Are you sure you want to delete customer +9613080203? This will also delete their orders and messages."
- Deletes customer and ALL related data:
  - Customer record
  - All their messages
  - All their orders
  - All their order items
  - Conversation context
- Shows success message

**Delete All Customers:**
- Click "🗑️ Delete All Customers" button at top right
- Confirms: "Are you sure you want to DELETE ALL CUSTOMERS? This will also delete all their orders and messages. This cannot be undone!"
- Clears entire database:
  - All customers
  - All messages
  - All orders
  - All order items
  - All conversation contexts
- Shows success message

---

## ⚠️ Safety Features

### Confirmation Dialogs

All delete actions require JavaScript confirmation:
```javascript
onsubmit="return confirm('⚠️ Are you sure...')"
```

**User must click "OK" to proceed** or "Cancel" to abort.

### Success Messages

After successful deletion:
```
✅ Order #123 deleted successfully!
✅ All messages deleted successfully!
✅ Customer deleted successfully!
✅ All customers deleted successfully!
```

### Visual Warnings

- Delete buttons are **RED** (#dc2626)
- Confirmation dialogs show **⚠️ warning icon**
- Messages clearly state what will be deleted

---

## 🎨 UI Features

### Delete Buttons Style

**Individual Delete Buttons:**
- Red background color
- Small size for table rows
- Trash icon (🗑️)
- Hover effect (darker red)

**Delete All Buttons:**
- Larger red button
- Positioned at top right
- Clear warning text
- Hover effect

### Button Placement

**Orders Page:**
- "Delete All Orders" - Top right next to "All Orders" title
- Individual "Delete" - Bottom right of each order card

**Messages Page:**
- "Delete All Messages" - Top right next to "Recent Messages" title

**Customers Page:**
- "Delete All Customers" - Top right next to "All Customers" title
- Individual "Delete" - In Actions column of table

---

## 💻 Technical Details

### Database Operations

**Orders:**
```sql
-- Delete single order
DELETE FROM order_items WHERE order_id = ?
DELETE FROM orders WHERE id = ?

-- Delete all orders
DELETE FROM order_items
DELETE FROM orders
```

**Messages:**
```sql
-- Delete all messages
DELETE FROM messages
```

**Customers:**
```sql
-- Delete single customer (cascade delete)
DELETE FROM messages WHERE customer_id = ?
DELETE FROM conversation_context WHERE customer_id = ?
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE customer_id = ?)
DELETE FROM orders WHERE customer_id = ?
DELETE FROM customers WHERE id = ?

-- Delete all customers (full wipe)
DELETE FROM messages
DELETE FROM conversation_context
DELETE FROM order_items
DELETE FROM orders
DELETE FROM customers
```

### Security

- All delete actions require POST method
- CSRF protection via session validation
- Admin login required
- Confirmation dialogs prevent accidental clicks
- Cascading deletes maintain database integrity

---

## 📋 Usage Examples

### Example 1: Delete Single Order

1. Go to `/admin/orders.php`
2. Find order you want to delete
3. Click "🗑️ Delete" button
4. Confirm in dialog box
5. See success message: "Order #123 deleted successfully! ✅"

### Example 2: Delete All Messages (Clean Slate)

1. Go to `/admin/messages.php`
2. Click "🗑️ Delete All Messages" button
3. Confirm: "Are you sure you want to DELETE ALL MESSAGES?"
4. Click OK
5. See success message: "All messages deleted successfully! ✅"
6. Message list now empty

### Example 3: Delete Customer with All Data

1. Go to `/admin/customers.php`
2. Find customer in table
3. Click "🗑️ Delete" in Actions column
4. Confirm: "This will also delete their orders and messages"
5. Click OK
6. Customer and all related data removed
7. See success message: "Customer deleted successfully! ✅"

### Example 4: Reset Everything (Testing/Development)

1. Go to `/admin/customers.php`
2. Click "🗑️ Delete All Customers"
3. Confirm the warning
4. Entire database cleared
5. Start fresh with clean slate

---

## ⚡ Quick Reference

| Page | Individual Delete | Bulk Delete | What Gets Deleted |
|------|------------------|-------------|-------------------|
| **Orders** | ✅ Yes | ✅ Yes | Order + Order Items |
| **Messages** | ❌ No | ✅ Yes | All Messages |
| **Customers** | ✅ Yes | ✅ Yes | Customer + Orders + Messages + Context |

---

## 🚨 Important Warnings

### ⚠️ Delete All Customers - MOST DESTRUCTIVE

This action deletes:
- ✅ All customer records
- ✅ All messages (incoming and outgoing)
- ✅ All orders and order items
- ✅ All conversation contexts

**Use case:** Fresh start, testing, development environment reset

### ⚠️ Delete Individual Customer - CASCADE DELETE

This action deletes:
- ✅ Customer record
- ✅ Their messages
- ✅ Their orders
- ✅ Their order items
- ✅ Their conversation context

**Use case:** Remove spam accounts, test accounts, or inactive customers

### ⚠️ Delete All Orders

This action deletes:
- ✅ All order records
- ✅ All order items

**Note:** Customer records remain intact

**Use case:** Clear old orders, reset order history

### ⚠️ Delete All Messages

This action deletes:
- ✅ All message history

**Note:** Customers and orders remain intact

**Use case:** Clear chat logs, free up database space

---

## ✅ Best Practices

1. **Always Backup First**
   - Export database before bulk delete operations
   - Test on development environment first

2. **Use Confirmations Carefully**
   - Read the confirmation dialog
   - Understand what will be deleted
   - Don't rush through warnings

3. **Individual Deletes for Precision**
   - Delete individual items when possible
   - More control over what gets removed

4. **Bulk Deletes for Clean Slate**
   - Use when testing/development
   - Use when clearing old data
   - Use when starting fresh

5. **Monitor Success Messages**
   - Verify deletion completed
   - Check success message appears
   - Refresh page to confirm deletion

---

## 🎉 Summary

✅ **Orders**: Delete individual orders or all orders
✅ **Messages**: Delete all messages (bulk only)
✅ **Customers**: Delete individual customers or all customers (with cascade)
✅ **Safety**: Confirmation dialogs on all delete actions
✅ **Feedback**: Success messages after deletion
✅ **Cascade**: Customer deletion removes all related data
✅ **Clean UI**: Red buttons, clear warnings, intuitive placement

Your admin dashboard now has full delete control! 🗑️🚀

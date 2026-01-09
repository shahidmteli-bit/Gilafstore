# Reusable Delete Confirmation Modal

## Overview
A professional, centered delete confirmation modal with high-class UI/UX design that can be used throughout the entire application (user and admin areas).

## Features
- ✅ Centered on screen with dark overlay
- ✅ Smooth animations (fade in, scale up, pulse)
- ✅ Professional design with red gradient
- ✅ Customizable title and message
- ✅ Backdrop blur effect
- ✅ Click outside to close
- ✅ Escape key to close
- ✅ Responsive design
- ✅ Easy to integrate

## Installation

### Step 1: Include the component in your PHP file

```php
<?php include __DIR__ . '/includes/delete-modal.php'; ?>
```

Or if you're in a subdirectory:

```php
<?php include __DIR__ . '/../includes/delete-modal.php'; ?>
```

## Usage Examples

### Example 1: Basic Delete Confirmation

```javascript
// When user clicks delete button
function deleteItem(itemId) {
    showDeleteConfirmation({
        title: 'Delete Item?',
        message: 'Are you sure you want to delete this item? This action cannot be undone.',
        onConfirm: function() {
            // Your delete logic here
            fetch('delete.php?id=' + itemId, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Item deleted successfully');
                        location.reload();
                    }
                });
        }
    });
}
```

### Example 2: Delete Address

```javascript
function deleteAddress(addressId) {
    showDeleteConfirmation({
        title: 'Delete Address?',
        message: 'Are you sure you want to delete this address? This action cannot be undone.',
        onConfirm: function() {
            fetch('manage_addresses.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete&id=' + addressId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Address deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            });
        }
    });
}
```

### Example 3: Delete User (Admin)

```javascript
function deleteUser(userId, userName) {
    showDeleteConfirmation({
        title: 'Delete User?',
        message: `Are you sure you want to delete user "${userName}"? This will permanently remove all their data.`,
        onConfirm: function() {
            fetch('admin/delete_user.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User deleted successfully');
                    location.reload();
                }
            });
        }
    });
}
```

### Example 4: Delete Product (Admin)

```javascript
function deleteProduct(productId) {
    showDeleteConfirmation({
        title: 'Delete Product?',
        message: 'This will remove the product from your store. Orders containing this product will not be affected.',
        onConfirm: function() {
            // Your delete product logic
            window.location.href = 'admin/delete_product.php?id=' + productId;
        }
    });
}
```

### Example 5: Delete Order

```javascript
function deleteOrder(orderId) {
    showDeleteConfirmation({
        title: 'Delete Order?',
        message: 'Are you sure you want to delete order #' + orderId + '? This action cannot be undone.',
        onConfirm: function() {
            // Your delete order logic
            console.log('Deleting order:', orderId);
        }
    });
}
```

## HTML Integration

### In your HTML, add delete buttons like this:

```html
<!-- Example 1: Simple button -->
<button onclick="deleteItem(123)">Delete</button>

<!-- Example 2: With icon -->
<button class="btn-delete" onclick="deleteAddress(456)">
    <i class="fas fa-trash"></i> Delete
</button>

<!-- Example 3: In a loop (PHP) -->
<?php foreach ($items as $item): ?>
    <button onclick="deleteItem(<?= $item['id'] ?>)">
        Delete
    </button>
<?php endforeach; ?>
```

## API Reference

### showDeleteConfirmation(options)

Shows the delete confirmation modal.

**Parameters:**
- `options` (Object)
  - `title` (string, optional) - Modal title. Default: "Delete Item?"
  - `message` (string, optional) - Confirmation message. Default: "Are you sure you want to delete this item? This action cannot be undone."
  - `onConfirm` (function, required) - Callback function executed when user confirms deletion

**Example:**
```javascript
showDeleteConfirmation({
    title: 'Custom Title',
    message: 'Custom message here',
    onConfirm: function() {
        // Your code here
    }
});
```

### closeDeleteConfirmation()

Closes the delete confirmation modal programmatically.

**Example:**
```javascript
closeDeleteConfirmation();
```

## Styling Customization

The modal uses these CSS classes that you can customize:

- `.delete-confirmation-modal` - Main modal container
- `.delete-confirmation-content` - Modal content box
- `.delete-confirmation-icon` - Trash icon circle
- `.delete-confirmation-actions` - Button container
- `.btn-delete-confirm` - Confirm button (red)
- `.btn-delete-cancel` - Cancel button (gray)

## Browser Support

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers

## Dependencies

- Font Awesome (for icons)
- Modern browser with ES6 support

## Notes

- The modal automatically closes when clicking outside
- Pressing Escape key also closes the modal
- Only one modal can be open at a time
- The modal is fully responsive and works on mobile devices

## Complete Example

```php
<?php
// Include the delete modal component
include __DIR__ . '/includes/delete-modal.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Page</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <h1>My Items</h1>
    
    <div class="item">
        <span>Item 1</span>
        <button onclick="deleteItem(1)">Delete</button>
    </div>
    
    <script>
    function deleteItem(itemId) {
        showDeleteConfirmation({
            title: 'Delete Item?',
            message: 'Are you sure you want to delete this item?',
            onConfirm: function() {
                // Your delete logic here
                console.log('Deleting item:', itemId);
                // Make API call, reload page, etc.
            }
        });
    }
    </script>
</body>
</html>
```

## Support

For issues or questions, refer to this documentation or check the component file at:
`includes/delete-modal.php`

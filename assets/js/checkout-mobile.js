// Mobile Checkout - Show Pay button only after payment method is selected
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const checkoutRight = document.querySelector('.checkout-right');
        
        if (paymentRadios.length > 0 && checkoutRight) {
            // Show payment section when any payment method is selected
            paymentRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        checkoutRight.classList.add('show-payment');
                        // Scroll to payment section smoothly
                        setTimeout(() => {
                            checkoutRight.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    }
                });
            });
            
            // Check if a payment method is already selected on page load
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            if (selectedPayment) {
                checkoutRight.classList.add('show-payment');
            }
        }
    }
});

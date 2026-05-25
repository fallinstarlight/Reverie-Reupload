
const form = document.getElementById('loginform');

form.addEventListener('submit', async function (event) {
    event.preventDefault();

    var username = document.getElementById('username').value;
    var password = document.getElementById('password').value;

    try {
        // Call the login API
        const response = await fetch('/api/endpoint/service.php?service=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                username: username, 
                password: password 
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Login successful
            console.log('Login successful:', data.user);
            sessionStorage.setItem('user', JSON.stringify(data.user));
            alert(`Welcome ${data.user.full_name || username}!`);
            switch(data.user.role){
                case 'employee':
                    window.location.href = '../dashboard.php';
                    break;
                case 'administrator':
                    window.location.href = '../Admin.php';
                    break;
            }
            
        } else {
            alert(data.error || 'Login failed. Please check your credentials.');
            console.error('Login error:', data.error);

            document.getElementById('password').value = '';
            document.getElementById('password').focus();
        }
        
    } catch (error) {
        console.error('Error during login:', error);
        alert('Connection error. Please check your internet connection and try again.');
    } 
});
    
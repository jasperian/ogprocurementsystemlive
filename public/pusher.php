<!DOCTYPE html>
<html>
<head>
    <title>Pusher Test</title>
    <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
</head>
<body>
    <h2>Pusher Test</h2>
    <div id="output">Waiting for event...</div>

    <script>
        // Enable pusher logging (for debugging)
        Pusher.logToConsole = true;

        var pusher = new Pusher('7f506782df003022bff5', { // Your key
            cluster: 'ap1'
        });

        var channel = pusher.subscribe('my-channel');
        channel.bind('my-event', function(data) {
            document.getElementById('output').innerHTML = "Received: " + JSON.stringify(data);
            console.log("Event data:", data);
        });
    </script>
</body>
</html>
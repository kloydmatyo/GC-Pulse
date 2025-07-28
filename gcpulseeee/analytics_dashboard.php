<!DOCTYPE html>
<html lang="en">
<head>
    <title>Post Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<h1>📊 Post Analytics</h1>

<!-- Post Activity Chart -->
<canvas id="postActivityChart"></canvas>

<!-- Engagement Chart -->
<canvas id="engagementChart"></canvas>

<!-- Category Distribution Chart -->
<canvas id="categoryChart"></canvas>

<script>
document.addEventListener('DOMContentLoaded', function () {
    fetch('fetch_post_analytics.php')
        .then(response => response.json())
        .then(data => {
            // Post Activity Over Time
            const postLabels = data.posts.map(item => item.date);
            const postCounts = data.posts.map(item => item.count);
            new Chart(document.getElementById('postActivityChart'), {
                type: 'line',
                data: {
                    labels: postLabels,
                    datasets: [{ label: 'Posts Over Time', data: postCounts, borderColor: 'blue', fill: false }]
                }
            });

            // Engagement (Likes & Comments)
            new Chart(document.getElementById('engagementChart'), {
                type: 'bar',
                data: {
                    labels: ['Likes', 'Comments'],
                    datasets: [{ label: 'Total', data: [data.engagement.likes, data.engagement.comments], backgroundColor: ['red', 'green'] }]
                }
            });

            // Post Categories
            const categoryLabels = data.categories.map(item => item.category);
            const categoryCounts = data.categories.map(item => item.count);
            new Chart(document.getElementById('categoryChart'), {
                type: 'pie',
                data: {
                    labels: categoryLabels,
                    datasets: [{ data: categoryCounts, backgroundColor: ['yellow', 'blue', 'purple'] }]
                }
            });
        });
});
</script>

</body>
</html>

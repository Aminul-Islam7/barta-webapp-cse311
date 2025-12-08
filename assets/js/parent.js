// Parent dashboard actions
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const childId = this.dataset.childId;
            const detailsContainer = document.getElementById('child-details-' + childId);

            if (detailsContainer.style.display === 'block') {
                detailsContainer.style.display = 'none';
                return;
            }

            fetch(`./parent/get_remaining_limit_stat.php?child_id=${childId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        detailsContainer.innerHTML = `<span style="color:red;">${data.error}</span>`;
                    } else {   						
                        detailsContainer.innerHTML = `
                            <div class="child-card__stat">
                                <span class="child-card__stat-label">Daily Limit:</span>
                                <span class="child-card__stat-value">${data.limit}</span>
                            </div>
                            <div class="child-card__stat">
                                <span class="child-card__stat-label">Messages Sent Today:</span>
                                <span class="child-card__stat-value">${data.sent_today}</span>
                            </div>
                            <div class="child-card__stat">
                                <span class="form-label" style="margin: 0rem;">Remaining Messages:</span>
                                <span class="child-card__stat-value">${data.remaining}</span>
                            </div>
                        `;
                    }
                    detailsContainer.style.display = 'block';
                })
                .catch(err => {
                    detailsContainer.innerHTML = `<span style="color:red;">Error fetching data</span>`;
                    detailsContainer.style.display = 'block';
                });
        });
    });
});

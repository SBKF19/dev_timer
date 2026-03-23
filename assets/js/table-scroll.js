/**
 * Gère le défilement automatique vers le tableau
 */
export function initTableScroll() {
    const urlParams = new URLSearchParams(window.location.search);
    
    const prevUrl = document.referrer;
    const prevParams = new URLSearchParams(prevUrl.split('?')[1]);

    const isPaging = urlParams.has('page') && urlParams.get('page') !== prevParams.get('page');
    
    const isSorting = (urlParams.has('sort') && urlParams.get('sort') !== prevParams.get('sort')) || 
                      (urlParams.has('direction') && urlParams.get('direction') !== prevParams.get('direction'));

    if (isPaging || isSorting) {
        const tableElement = document.getElementById('entries-table');
        
        if (tableElement) {
            setTimeout(() => {
                tableElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 150);
        }
    }
}

document.addEventListener('DOMContentLoaded', initTableScroll);
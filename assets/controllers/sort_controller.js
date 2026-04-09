import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    sortBy(event) {
        const th = event.currentTarget;
        const tbody = th.closest('table').querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const index = Array.from(th.parentNode.children).indexOf(th);
        const isAscending = th.dataset.order !== 'asc';

        rows.sort((a, b) => {
            const valA = a.children[index].innerText.trim();
            const valB = b.children[index].innerText.trim();

            return isAscending 
                ? valA.localeCompare(valB, undefined, {numeric: true, sensitivity: 'base'})
                : valB.localeCompare(valA, undefined, {numeric: true, sensitivity: 'base'});
        });

        th.dataset.order = isAscending ? 'asc' : 'desc';
        this.updateIcons(th);

        rows.forEach(row => tbody.appendChild(row));
    }

    updateIcons(activeTh) {
        activeTh.parentNode.querySelectorAll('span').forEach(s => s.innerText = '↑');
        activeTh.querySelector('span').innerText = activeTh.dataset.order === 'asc' ? '↑' : '↓';
    }
}
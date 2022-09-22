document.addEventListener("DOMContentLoaded", al_load_papers);

function al_load_papers() {
    const elements = document.getElementsByClassName("al_paper_list");

    Array.from(elements).forEach(async element => {
        const orcids = element.getAttribute('data-orcids');
        const npapers = element.getAttribute('data-npapers');

        const response = await fetch('/wp-json/arxiv_list/v1/generate', {
            method: 'POST',
            body: JSON.stringify({
                orcids: orcids, 
                npapers: npapers
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();
        element.innerHTML = data['response'];
    });
}
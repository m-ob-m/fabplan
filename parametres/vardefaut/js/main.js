"use strict";

function openGenericParameters(id = null)
{
	let next_page = [ROOT_URL + "/parametres/vardefaut/index.php"];
	if(id !== null && id !== "")
	{
		next_page.push("?id=", id);
	}
	window.location.assign(next_page.join(""));
}
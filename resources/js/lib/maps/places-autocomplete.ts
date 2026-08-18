export type AddressAutocompleteSuggestion = {
    placeId: string;
    description: string;
    mainText: string;
    secondaryText: string;
    placePrediction: google.maps.places.PlacePrediction;
};

export async function loadPlacesLibrary(): Promise<google.maps.PlacesLibrary> {
    if (!window.google?.maps?.importLibrary) {
        throw new Error('Google Maps no está listo.');
    }

    return (await window.google.maps.importLibrary(
        'places',
    )) as google.maps.PlacesLibrary;
}

export async function fetchAddressSuggestions(
    input: string,
    sessionToken: google.maps.places.AutocompleteSessionToken,
): Promise<AddressAutocompleteSuggestion[]> {
    const { AutocompleteSuggestion } = await loadPlacesLibrary();

    const { suggestions } =
        await AutocompleteSuggestion.fetchAutocompleteSuggestions({
            input,
            sessionToken,
            includedRegionCodes: ['mx'],
        });

    return suggestions.flatMap((suggestion) => {
        const prediction = suggestion.placePrediction;

        if (!prediction) {
            return [];
        }

        const mainText = prediction.mainText?.text ?? prediction.text.text;
        const secondaryText = prediction.secondaryText?.text ?? '';

        return [
            {
                placeId: prediction.placeId,
                description: secondaryText
                    ? `${mainText}, ${secondaryText}`
                    : mainText,
                mainText,
                secondaryText,
                placePrediction: prediction,
            },
        ];
    });
}

export async function resolvePlaceFromSuggestion(
    suggestion: AddressAutocompleteSuggestion,
): Promise<google.maps.places.Place> {
    const place = suggestion.placePrediction.toPlace();

    await place.fetchFields({
        fields: ['id', 'displayName', 'formattedAddress', 'location'],
    });

    return place;
}

export async function createAutocompleteSessionToken(): Promise<google.maps.places.AutocompleteSessionToken> {
    const { AutocompleteSessionToken } = await loadPlacesLibrary();

    return new AutocompleteSessionToken();
}

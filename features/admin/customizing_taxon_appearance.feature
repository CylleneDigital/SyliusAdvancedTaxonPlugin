@customizing_taxon_appearance
Feature: Customizing the appearance of a taxon
    In order to make the taxonomy recognizable in the storefront
    As an Administrator
    I want to give a taxon a color, an icon or a pictogram

    Background:
        Given the store operates on a single channel
        And the store has locale "English (United States)"
        And I am logged in as an administrator

    @ui
    Scenario: Adding a taxon with a color and an icon
        When I want to create a new taxon
        And I specify its code as "watches"
        And I set the taxon "name" to "Watches" in "English (United States)"
        And I set the taxon "slug" to "watches" in "English (United States)"
        And I set its color to "#336699"
        And I choose the "folder" icon
        And I add it
        Then I should be notified that it has been successfully created
        And the "Watches" taxon should have the "#336699" color
        And the "Watches" taxon should use the "folder" icon

    @ui
    Scenario: Uploading a pictogram stores it in the Sylius image storage
        When I want to create a new taxon
        And I specify its code as "pictograms"
        And I set the taxon "name" to "Pictograms" in "English (United States)"
        And I set the taxon "slug" to "pictograms" in "English (United States)"
        And I upload a pictogram
        And I add it
        Then I should be notified that it has been successfully created
        And the pictogram of the "Pictograms" taxon should be stored in the Sylius image storage

    @ui @javascript
    Scenario: Choosing an icon in the icon picker
        Given the store classifies its products as "Watches" with "watches" code
        When I want to modify the "Watches" taxon
        And I pick the "tabler:folder" icon in the icon picker
        Then the icon field should hold "tabler:folder"
        When I save my changes
        Then I should be notified that it has been successfully edited
        And the "Watches" taxon should use the "tabler:folder" icon
